<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserRadiusProfile;
use App\Enum\AnalyticalEventType;
use App\Enum\EmailConfirmationStrategy;
use App\Enum\OSTypes;
use App\Enum\UserProvider;
use App\Enum\UserRadiusProfileStatus;
use App\RadiusDb\Entity\RadiusUser;
use App\RadiusDb\Repository\RadiusUserRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\ExpirationProfileService;
use App\Service\GetSettings;
use App\Utils\CacheUtils;
use DateTime;
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
    private array $settings;

    /**
     * @param SettingRepository $settingRepository
     * @param EventActions $eventActions ,
     * @param GetSettings $getSettings
     * @param UserRepository $userRepository
     * @param UserExternalAuthRepository $userExternalAuthRepository
     * @param ExpirationProfileService $expirationProfileService
     */
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly EventActions $eventActions,
        private readonly GetSettings $getSettings,
        private readonly UserRepository $userRepository,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly ExpirationProfileService $expirationProfileService,
    ) {
        $this->settings = $this->getSettings($settingRepository);
    }

    #[Route('/profile/android', name: 'profile_android')]
    public function profileAndroid(
        RadiusUserRepository $radiusUserRepository,
        UserRepository $userRepository,
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

        if ($this->checkUserStatus($user) === true) {
            return $this->redirectToRoute('app_landing');
        }

        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $user]);

        $radiusUser = $this->createOrUpdateRadiusUser(
            $user,
            $radiusUserRepository,
            $radiusProfileRepository,
            $userRepository,
            $this->settings['RADIUS_REALM_NAME']
        );

        $expirationDate = $this->expirationProfileService->calculateExpiration(
            $userExternalAuth->getProvider(),
            $userExternalAuth->getProviderId(),
            (new UserRadiusProfile())->setIssuedAt(
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
            base64_encode($radiusUser->getValue()),
            $this->settings['DOMAIN_NAME'],
            $this->settings['RADIUS_TLS_NAME'],
            $this->settings['DISPLAY_NAME'],
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
            'platform' => $this->settings['PLATFORM_MODE'],
            'type' => OSTypes::ANDROID,
            'ip' => $request->getClientIp(),
        ];

        // Save the event Action using the service
        $this->eventActions->saveEvent($user, AnalyticalEventType::DOWNLOAD_PROFILE, new DateTime(), $eventMetadata);

        return $response;
    }

    #[Route('/profile/ios.mobileconfig', name: 'profile_ios')]
    public function profileIos(
        RadiusUserRepository $radiusUserRepository,
        UserRepository $userRepository,
        UserRadiusProfileRepository $radiusProfileRepository,
        Request $request
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->checkUserStatus($user) === true) {
            return $this->redirectToRoute('app_landing');
        }

        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $user]);

        $radiusUser = $this->createOrUpdateRadiusUser(
            $user,
            $radiusUserRepository,
            $radiusProfileRepository,
            $userRepository,
            $this->settings['RADIUS_REALM_NAME']
        );

        $expirationDate = $this->expirationProfileService->calculateExpiration(
            $userExternalAuth->getProvider(),
            $userExternalAuth->getProviderId(),
            (new UserRadiusProfile())->setIssuedAt(
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
            $this->settings['DOMAIN_NAME'],
            $this->settings['RADIUS_TLS_NAME'],
            $this->settings['DISPLAY_NAME'],
            $this->settings['PAYLOAD_IDENTIFIER'],
            $this->settings['OPERATOR_NAME'],
            $this->settings['NAI_REALM'],
            $this->settings['PROFILES_ENCRYPTION_TYPE_IOS_ONLY'],
            $expirationDate['limitTime']->format('Y-m-d\TH:i:s\Z'),
        ], $profile);

        //iOS Specific
        $randomfactorIdentifier = bin2hex(random_bytes(16));
        $randomFileName = 'ios_unsigned_' . $randomfactorIdentifier . '.mobileconfig';
        $randomSignedFileName = 'ios_signed_' . $randomfactorIdentifier . '.mobileconfig';
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
            throw new RuntimeException('Signing failed: ' . $exception->getMessage());
        }
        $signedProfileContents = file_get_contents($signedFilePath);
        unlink($signedFilePath);

        $response = new Response($signedProfileContents);

        $response->headers->set('Content-Type', 'application/x-apple-aspen-config');


        // Save the event Action using the service
        $userAgent = $request->headers->get('User-Agent');
        $eventMetadata = [];
        if (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) {
            $eventMetadata = [
                'platform' => $this->settings['PLATFORM_MODE'],
                'type' => OSTypes::IOS,
                'ip' => $request->getClientIp(),
            ];
        } elseif (stripos($userAgent, 'Mac OS') !== false) {
            $eventMetadata = [
                'platform' => $this->settings['PLATFORM_MODE'],
                'type' => OSTypes::MACOS,
                'ip' => $request->getClientIp(),
            ];
        }

        $this->eventActions->saveEvent($user, AnalyticalEventType::DOWNLOAD_PROFILE, new DateTime(), $eventMetadata);

        return $response;
    }

    #[Route('/profile/windows', name: 'profile_windows')]
    public function profileWindows(
        RadiusUserRepository $radiusUserRepository,
        UserRepository $userRepository,
        UrlGeneratorInterface $urlGenerator,
        UserRadiusProfileRepository $radiusProfileRepository,
        Request $request
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->checkUserStatus($user) === true) {
            return $this->redirectToRoute('app_landing');
        }

        $radiususer = $this->createOrUpdateRadiusUser(
            $user,
            $radiusUserRepository,
            $radiusProfileRepository,
            $userRepository,
            $this->settings['RADIUS_REALM_NAME']
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
            $radiususer->getUsername(),
            $radiususer->getValue(),
            $this->generateWindowsUuid(),
            $this->settings['DOMAIN_NAME'],
            $this->settings['RADIUS_TLS_NAME'],
            $this->settings['RADIUS_TRUSTED_ROOT_CA_SHA1_HASH'],
            $this->settings['DISPLAY_NAME'],
        ], $profile);
        //Windows Specific
        $randomfactorIdentifier = bin2hex(random_bytes(16));
        $randomFileName = 'windows_unsigned_' . $randomfactorIdentifier . '.xml';
        $randomSignedFileName = 'windows_signed_' . $randomfactorIdentifier . '.xml';
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
            throw new RuntimeException('Signing failed: ' . $exception->getMessage());
        }
        $uuid = uniqid("", true);
        $signedProfileContents = file_get_contents($signedFilePath);
        unlink($signedFilePath);
        $cache = new CacheUtils();
        $cache->write('profile_' . $uuid, $signedProfileContents);

        $eventMetadata = [
            'platform' => $this->settings['PLATFORM_MODE'],
            'type' => OSTypes::WINDOWS,
            'ip' => $request->getClientIp(),
        ];

        // Save the event Action using the service
        $this->eventActions->saveEvent($user, AnalyticalEventType::DOWNLOAD_PROFILE, new DateTime(), $eventMetadata);

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
        UserRepository $userRepository,
        string $realmName
    ): RadiusUser {
        $radiusProfile = $radiusProfileRepository->findOneBy(
            ['user' => $user, 'status' => UserRadiusProfileStatus::ACTIVE]
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
            $radiusProfile->setStatus(UserRadiusProfileStatus::ACTIVE);
            $radiusProfile->setIssuedAt(new \DateTime());

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

    private function getSettings(SettingRepository $settingRepository): array
    {
        $settings = $settingRepository->findAll();
        return array_reduce($settings, function ($carry, $item) {
            $carry[$item->getName()] = $item->getValue();
            return $carry;
        }, []);
    }

    private function checkUserStatus(User $user): bool
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        if ($user->getDeletedAt()) {
            $this->addFlash(
                'error',
                'Your account has been deleted. Please, for more information contact our support.'
            );
            $this->redirectToRoute('app_landing');
            return true;
        }

        if ($user->getBannedAt()) {
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
            $data['USER_VERIFICATION']['value'] === EmailConfirmationStrategy::EMAIL
        ) {
            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);
            if ($userExternalAuths === UserProvider::EMAIL) {
                $this->addFlash(
                    'error',
                    'Your account is not verified to download a profile, 
                    before being able to download a profile you need to confirm your account by 
                    clicking on the link send to you via email!'
                );
            } elseif ($userExternalAuths === UserProvider::PHONE_NUMBER) {
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
        return false;
    }
}
