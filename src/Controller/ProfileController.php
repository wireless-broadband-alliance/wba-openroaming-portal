<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserRadiusProfile;
use App\Enum\AnalyticalEventType;
use App\Enum\OperationMode;
use App\Enum\OSTypes;
use App\Enum\UserProvider;
use App\Enum\UserRadiusProfileStatus;
use App\RadiusDb\Entity\RadiusUser;
use App\RadiusDb\Repository\RadiusUserRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Service\EventActions;
use App\Service\ExpirationProfileService;
use App\Service\TwoFAService;
use App\Utils\CacheUtils;
use DateTime;
use DateTimeInterface;
use Exception;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProfileController extends AbstractController
{
    /**
     * @param EventActions $eventActions ,
     */
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly EventActions $eventActions,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly ExpirationProfileService $expirationProfileService,
        private readonly TwoFAService $twoFAService,
    ) {
    }

    /**
     * @throws Exception
     */
    #[Route('/profile/android', name: 'profile_android')]
    public function profileAndroid(
        RadiusUserRepository $radiusUserRepository,
        UserRadiusProfileRepository $radiusProfileRepository,
        Request $request
    ): Response {
        if (!file_exists('/var/www/openroaming/signing-keys/ca.pem')) {
            throw new RuntimeException("CA.pem is missing");
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->checkUserStatus($user)) {
            return $this->redirectToRoute('app_landing');
        }

        if ($this->twoFAService->isTwoFARequired($user)) {
            return $this->redirectToRoute('app_landing');
        }

        $session = $request->getSession();
        if ($this->twoFAService->twoFAisActive($user) && !$session->has('2fa_verified_landing')) {
            return $this->redirectToRoute('app_landing');
        }


        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $user]);

        $radiusUser = $this->createOrUpdateRadiusUser(
            $user,
            $radiusUserRepository,
            $radiusProfileRepository,
            $this->settingRepository->findOneBy(['name' => 'RADIUS_REALM_NAME'])->getValue()
        );

        $expirationDate = $this->expirationProfileService->calculateExpiration(
            $userExternalAuth->getProvider(),
            $userExternalAuth->getProviderId(),
            new UserRadiusProfile()->setIssuedAt(
                new DateTime()
            ), // Pass a new DateTime if the user does not have a profile with the account
            '../signing-keys/cert.pem'
        );

        $profile = file_get_contents('../profile_templates/android/profile.xml');
        $profile = str_replace([
            '@USERNAME@',
            '@PASSWORD@',
            '@DOMAIN_NAME@',
            '@RADIUS_TLS_NAME@',
            '@DISPLAY_NAME@',
            '@EXPIRATION_DATE@'
        ], [
            $radiusUser->getUsername(),
            base64_encode((string)$radiusUser->getValue()),
            $this->settingRepository->findOneBy(['name' => 'DOMAIN_NAME'])->getValue(),
            $this->settingRepository->findOneBy(['name' => 'RADIUS_TLS_NAME'])->getValue(),
            $this->settingRepository->findOneBy(['name' => 'DISPLAY_NAME'])->getValue(),
            $expirationDate['limitTime']->format('Y-m-d')
        ], $profile);
        $profileTemplate = file_get_contents('../profile_templates/android/template.txt');
        $ca = file_get_contents('../signing-keys/ca.pem');
        $ca = str_replace(
            ["-----BEGIN CERTIFICATE-----\n", "-----END CERTIFICATE-----\n", "-----END CERTIFICATE-----"],
            '',
            $ca
        );
        $profileTemplate = str_replace('@CA@', $ca, $profileTemplate);
        $profileTemplate = str_replace('@PROFILE@', base64_encode($profile), $profileTemplate);
        $response = new Response(base64_encode($profileTemplate));

        $response->headers->set('Content-Type', 'application/x-wifi-config');
        $response->headers->set('Content-Transfer-Encoding', 'base64');

        $eventMetadata = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'platform' => $this->settingRepository->findOneBy(['name' => 'PLATFORM_MODE'])->getValue(),
            'type' => OSTypes::ANDROID->value,
        ];

        // Save the event Action using the service
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::DOWNLOAD_PROFILE->value,
            new DateTime(),
            $eventMetadata
        );

        return $response;
    }

    #[Route('/profile/ios.mobileconfig', name: 'profile_ios')]
    public function profileIos(
        RadiusUserRepository $radiusUserRepository,
        UserRadiusProfileRepository $radiusProfileRepository,
        Request $request
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->checkUserStatus($user)) {
            return $this->redirectToRoute('app_landing');
        }

        if ($this->twoFAService->isTwoFARequired($user)) {
            return $this->redirectToRoute('app_landing');
        }

        $session = $request->getSession();
        if ($this->twoFAService->twoFAisActive($user) && !$session->has('2fa_verified_landing')) {
            return $this->redirectToRoute('app_landing');
        }

        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $user]);

        $radiusUser = $this->createOrUpdateRadiusUser(
            $user,
            $radiusUserRepository,
            $radiusProfileRepository,
            $this->settingRepository->findOneBy(['name' => 'RADIUS_REALM_NAME'])->getValue()
        );

        $expirationDate = $this->expirationProfileService->calculateExpiration(
            $userExternalAuth->getProvider(),
            $userExternalAuth->getProviderId(),
            new UserRadiusProfile()->setIssuedAt(
                new DateTime()
            ), // Pass a new DateTime if the user does not have a profile with the account
            '../signing-keys/cert.pem'
        );

        $profile = file_get_contents('../profile_templates/iphone_templates/template.xml');
        $profile = str_replace([
            '@USERNAME@',
            '@PASSWORD@',
            '@DOMAIN_NAME@',
            '@RADIUS_TLS_NAME@',
            '@DISPLAY_NAME@',
            '@IOS_PAYLOAD_IDENTIFIER@',
            '@IOS_OPERATOR_NAME@',
            '@NAI_REALM@',
            '@PROFILES_ENCRYPTION_TYPE_IOS_ONLY@',
            '@EXPIRATION_DATE@',
        ], [
            $radiusUser->getUsername(),
            $radiusUser->getValue(),
            $this->settingRepository->findOneBy(['name' => 'DOMAIN_NAME'])->getValue(),
            $this->settingRepository->findOneBy(['name' => 'RADIUS_TLS_NAME'])->getValue(),
            $this->settingRepository->findOneBy(['name' => 'DISPLAY_NAME'])->getValue(),
            $this->settingRepository->findOneBy(['name' => 'PAYLOAD_IDENTIFIER'])->getValue(),
            $this->settingRepository->findOneBy(['name' => 'OPERATOR_NAME'])->getValue(),
            $this->settingRepository->findOneBy(['name' => 'NAI_REALM'])->getValue(),
            $this->settingRepository->findOneBy(['name' => 'PROFILES_ENCRYPTION_TYPE_IOS_ONLY'])->getValue(),
            $expirationDate['limitTime']->format('Y-m-d\TH:i:s\Z'),
        ], $profile);

        //iOS Specific
        $randomFactorIdentifier = bin2hex(random_bytes(16));
        $randomFileName = 'ios_unsigned_' . $randomFactorIdentifier . '.mobileconfig';
        $randomSignedFileName = 'ios_signed_' . $randomFactorIdentifier . '.mobileconfig';
        $signedFilePath = '/tmp/' . $randomSignedFileName;
        $unSignedFilePath = '/tmp/' . $randomFileName;
        file_put_contents($unSignedFilePath, $profile);
        $command = [
            'openssl',
            'smime',
            '-sign',
            '-in',
            $unSignedFilePath,
            '-out',
            $signedFilePath,
            '-signer',
            '/var/www/openroaming/signing-keys/cert.pem',
            '-inkey',
            '/var/www/openroaming/signing-keys/privkey.pem',
            '-certfile',
            '/var/www/openroaming/signing-keys/fullchain.pem',
            '-outform',
            'der',
            '-nodetach'
        ];
        $process = new Process($command);
        try {
            $process->mustRun();
            unlink($unSignedFilePath);
        } catch (ProcessFailedException $exception) {
            throw new RuntimeException(
                'Signing failed: ' . $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }
        $signedProfileContents = file_get_contents($signedFilePath);
        unlink($signedFilePath);

        $response = new Response($signedProfileContents);

        $response->headers->set('Content-Type', 'application/x-apple-aspen-config');


        // Save the event Action using the service
        $userAgent = $request->headers->get('User-Agent');
        $eventMetadata = [];
        if (stripos((string)$userAgent, 'iPhone') !== false || stripos((string)$userAgent, 'iPad') !== false) {
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'platform' => $this->settingRepository->findOneBy(['name' => ['PLATFORM_MODE']])->getValue(),
                'type' => OSTypes::IOS->value,
            ];
        } elseif (stripos((string)$userAgent, 'Mac OS') !== false) {
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'platform' => $this->settingRepository->findOneBy(['name' => ['PLATFORM_MODE']])->getValue(),
                'type' => OSTypes::MACOS->value
            ];
        }

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::DOWNLOAD_PROFILE->value,
            new DateTime(),
            $eventMetadata
        );

        return $response;
    }

    #[Route('/profile/windows', name: 'profile_windows')]
    public function profileWindows(
        RadiusUserRepository $radiusUserRepository,
        UrlGeneratorInterface $urlGenerator,
        UserRadiusProfileRepository $radiusProfileRepository,
        Request $request
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->checkUserStatus($user)) {
            return $this->redirectToRoute('app_landing');
        }

        if ($this->twoFAService->isTwoFARequired($user)) {
            return $this->redirectToRoute('app_landing');
        }

        $session = $request->getSession();
        if ($this->twoFAService->twoFAisActive($user) && !$session->has('2fa_verified_landing')) {
            return $this->redirectToRoute('app_landing');
        }

        $radiusUser = $this->createOrUpdateRadiusUser(
            $user,
            $radiusUserRepository,
            $radiusProfileRepository,
            $this->settingRepository->findOneBy(['name' => 'RADIUS_REALM_NAME'])->getValue()
        );
        $profile = file_get_contents('../profile_templates/windows/template.xml');
        $profile = str_replace([
            '@USERNAME@',
            '@PASSWORD@',
            '@UUID@',
            '@DOMAIN_NAME@',
            '@RADIUS_TLS_NAME@',
            '@RADIUS_TRUSTED_ROOT_CA_SHA1_HASH@',
            '@DISPLAY_NAME@',
        ], [
            $radiusUser->getUsername(),
            $radiusUser->getValue(),
            $this->generateWindowsUuid(),
            $this->settingRepository->findOneBy(['name' => 'DOMAIN_NAME'])->getValue(),
            $this->settingRepository->findOneBy(['name' => 'RADIUS_TLS_NAME'])->getValue(),
            $this->settingRepository->findOneBy(['name' => 'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH'])->getValue(),
            $this->settingRepository->findOneBy(['name' => 'DISPLAY_NAME'])->getValue(),
        ], $profile);

        //Windows Specific
        $randomFactorIdentifier = bin2hex(random_bytes(16));
        $randomFileName = 'windows_unsigned_' . $randomFactorIdentifier . '.xml';
        $randomSignedFileName = 'windows_signed_' . $randomFactorIdentifier . '.xml';
        $signedFilePath = '/tmp/' . $randomSignedFileName;
        $unSignedFilePath = '/tmp/' . $randomFileName;
        file_put_contents($unSignedFilePath, $profile);
        $command = [
            'xmlsec1',
            '--sign',
            '--pkcs12',
            '/var/www/openroaming/signing-keys/windowsKey.pfx',
            '--pwd',
            "",
            '--output',
            $signedFilePath,
            $unSignedFilePath,
        ];
        $process = new Process($command);
        try {
            $process->mustRun();
            unlink($unSignedFilePath);
        } catch (ProcessFailedException $exception) {
            throw new RuntimeException(
                'Signing failed: ' . $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }
        $uuid = uniqid("", true);
        $signedProfileContents = file_get_contents($signedFilePath);
        unlink($signedFilePath);
        $cache = new CacheUtils();
        $cache->write('profile_' . $uuid, $signedProfileContents);

        $eventMetadata = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'platform' => $this->settingRepository->findOneBy(['name' => ['PLATFORM_MODE']])->getValue(),
            'type' => OSTypes::WINDOWS->value,
        ];

        // Save the event Action using the service
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::DOWNLOAD_PROFILE->value,
            new DateTime(),
            $eventMetadata
        );

        return $this->redirect(
            'ms-settings:wifi-provisioning?uri=' . $urlGenerator->generate(
                'profile_windows_serve',
                ['uuid' => $uuid],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
        );
    }

    #[Route('/profile/windows_serve', name: 'profile_windows_serve')]
    public function profileWindowsServe(Request $request): Response
    {
        $cache = new CacheUtils();
        $profileData = $cache->read('profile_' . $request->query->get("uuid"));
        if (!$profileData) {
            $this->addFlash('error', 'Profile not found');
            return $this->redirectToRoute('app_landing');
        }
        $response = new Response($profileData);
        $response->headers->set('Content-Type', 'application/xaml+xml');
        return $response;
    }

    private function generateToken($length = 16)
    {
        $stringSpace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $pieces = [];
        $max = mb_strlen($stringSpace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces[] = $stringSpace[random_int(0, $max)];
        }
        return implode('', $pieces);
    }

    private function generateWindowsUuid()
    {
        $format = '%04x%04x-%04x-%04x-%04x-%04x%04x%04x';

        return sprintf(
            $format,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff), // 8 hex characters
            mt_rand(0, 0xffff), // 4 hex characters
            mt_rand(0, 0x0fff) | 0x4000, // 4 hex characters, 13th bit set to 0100 (version 4 UUID)
            mt_rand(0, 0x3fff) | 0x8000, // 4 hex characters, 17th bit set to 1000 (variant 1 UUID)
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff) // 12 hex characters
        );
    }

    /**
     * @throws Exception
     */
    private function createOrUpdateRadiusUser(
        User $user,
        RadiusUserRepository $radiusUserRepository,
        UserRadiusProfileRepository $radiusProfileRepository,
        string $realmName
    ): RadiusUser {
        $radiusProfile = $radiusProfileRepository->findOneBy(
            ['user' => $user, 'status' => UserRadiusProfileStatus::ACTIVE->value]
        );
        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $user]);

        if (!$radiusProfile) {
            $radiusProfile = new UserRadiusProfile();

            $androidLimit = 32;
            $realmSize = strlen($realmName) + 1;
            $username = $this->generateToken($androidLimit - $realmSize) . "@" . $realmName;
            $token = $this->generateToken($androidLimit - $realmSize);
            $radiusProfile->setUser($user);
            $radiusProfile->setRadiusToken($token);
            $radiusProfile->setRadiusUser($username);
            $radiusProfile->setStatus(UserRadiusProfileStatus::ACTIVE->value);
            $radiusProfile->setIssuedAt(new DateTime());

            // Get the expiration date from the service
            $expirationData = $this->expirationProfileService->calculateExpiration(
                $userExternalAuth->getProvider(),
                $userExternalAuth->getProviderId(),
                $radiusProfile,
                '../signing-keys/cert.pem'
            );

            // Set the valid_until property
            $radiusProfile->setValidUntil($expirationData['limitTime']);

            $radiusUser = new RadiusUser();
            $radiusUser->setUsername($username);
            $radiusUser->setAttribute('Cleartext-Password');
            $radiusUser->setOp(':=');
            $radiusUser->setValue($token);
            $radiusUserRepository->save($radiusUser, true);
            $radiusProfileRepository->save($radiusProfile, true);
        } else {
            $radiusUser = $radiusUserRepository->findOneBy([
                'username' => $radiusProfile->getRadiusUser(),
            ]);
            if (!$radiusUser) {
                /* In cases where we have don't have the profile with a $radiusUser.
                This logic is also required to not break the portal when the account profiles
                have been revoked previously */
                $androidLimit = 32;
                $realmSize = strlen($realmName) + 1;
                $username = $this->generateToken($androidLimit - $realmSize) . "@" . $realmName;
                $token = $this->generateToken($androidLimit - $realmSize);

                $radiusUser = new RadiusUser();
                $radiusUser->setUsername($username);
                $radiusUser->setAttribute('Cleartext-Password');
                $radiusUser->setOp(':=');
                $radiusUser->setValue($token);
                $radiusUserRepository->save($radiusUser, true);
                $radiusProfileRepository->save($radiusProfile, true);
            }
        }

        return $radiusUser;
    }

    private function checkUserStatus(User $user): bool
    {
        if ($user->getDeletedAt() instanceof DateTimeInterface) {
            $this->addFlash(
                'error',
                'Your account has been deleted. Please, for more information contact our support.'
            );
            $this->redirectToRoute('app_landing');
            return true;
        }

        if ($user->getBannedAt() instanceof DateTimeInterface) {
            $this->addFlash('error', 'Your account is banned. Please, for more information contact our support.');
            $this->redirectToRoute('app_landing');
            return true;
        }

        if ($user->isDisabled()) {
            $this->addFlash('error', 'Your account currently is disabled.');
            $this->redirectToRoute('app_landing');
            return true;
        }

        if (
            !$user->isVerified() &&
            $this->settingRepository->findOneBy(
                ['name' => 'USER_VERIFICATION']
            )->getValue() === OperationMode::ON->value
        ) {
            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);
            if ($userExternalAuths === UserProvider::EMAIL->value) {
                $this->addFlash(
                    'error',
                    'Your account is not verified to download a profile, 
                    before being able to download a profile you need to confirm your account by 
                    clicking on the link send to you via email!'
                );
            } elseif ($userExternalAuths === UserProvider::PHONE_NUMBER->value) {
                $this->addFlash(
                    'error',
                    'Your account is not verified to download a profile, 
                    before being able to download a profile you need to confirm your account by 
                   inserting the code send to you via SMS!'
                );
            }
            $this->redirectToRoute('app_landing');
            return true;
        }

        if ($user->isForgotPasswordRequest()) {
            $this->addFlash('error', 'Your account is currently with a request pending approval!');
            $this->redirectToRoute('app_landing');
            return true;
        }

        return false;
    }
}
