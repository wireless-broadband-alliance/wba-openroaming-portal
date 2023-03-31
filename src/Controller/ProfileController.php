<?php

namespace App\Controller;


use App\RadiusDb\Entity\RadiusUser;
use App\RadiusDb\Repository\RadiusUserRepository;
use App\Repository\UserRepository;
use App\Utils\CacheUtils;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProfileController extends AbstractController
{

    #[Route('/profile/android', name: 'profile_android')]
    public function profileAndroid(ManagerRegistry $entityManager, RadiusUserRepository $radiusUserRepository, UserRepository $userRepository): Response
    {
        if (!file_exists('/var/www/openroaming/signing-keys/ca.pem')) {
            throw new RuntimeException("CA.pem is missing");
        }
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        $radiususer = $this->createOrUpdateRadiusUser($user, $radiusUserRepository, $userRepository);

        $profile = file_get_contents('../profile_templates/android/profile.xml');
        $profile = str_replace('@USERNAME@', $radiususer->getUsername(), $profile);
        $profile = str_replace('@PASSWORD@', base64_encode($radiususer->getValue()), $profile);
        $profileTemplate = file_get_contents('../profile_templates/android/template.txt');
        $ca = file_get_contents('../signing-keys/ca.pem');
        $ca = str_replace(["-----BEGIN CERTIFICATE-----\n", "-----END CERTIFICATE-----\n", "-----END CERTIFICATE-----"], '', $ca);
        $profileTemplate = str_replace('@CA@', $ca, $profileTemplate);
        $profileTemplate = str_replace('@PROFILE@', base64_encode($profile), $profileTemplate);
        $response = new Response(base64_encode($profileTemplate));

        $response->headers->set('Content-Type', 'application/x-wifi-config');
        $response->headers->set('Content-Transfer-Encoding', 'base64');

        return $response;
    }

    #[Route('/profile/ios.mobileconfig', name: 'profile_ios')]
    public function profileIos(ManagerRegistry $entityManager, RadiusUserRepository $radiusUserRepository, UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        $radiususer = $this->createOrUpdateRadiusUser($user, $radiusUserRepository, $userRepository);

        $profile = file_get_contents('../profile_templates/iphone_templates/template.xml');
        $profile = str_replace('@USERNAME@', $radiususer->getUsername(), $profile);
        $profile = str_replace('@PASSWORD@', $radiususer->getValue(), $profile);

        $response = new Response($profile);

        $response->headers->set('Content-Type', 'application/x-apple-aspen-config');

        return $response;
    }

    #[Route('/profile/windows', name: 'profile_windows')]
    public function profileWindows(ManagerRegistry $entityManager, RadiusUserRepository $radiusUserRepository, UserRepository $userRepository, UrlGeneratorInterface $urlGenerator): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        //Check if we have a signing Pfx Certificate
        if (!file_exists('/var/www/openroaming/signing-keys/windowsKey.pfx')) {
            throw new RuntimeException("Windows signing Pfx certificate is missing, did you forget to build it?");
        }
        $radiususer = $this->createOrUpdateRadiusUser($user, $radiusUserRepository, $userRepository);
        $profile = file_get_contents('../profile_templates/windows/template.xml');
        $profile = str_replace('@USERNAME@', $radiususer->getUsername(), $profile);
        $profile = str_replace('@PASSWORD@', $radiususer->getValue(), $profile);
        $profile = str_replace('@UUID@', $this->generateWindowsUuid(), $profile);
        ///Windows Specific
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
        return $this->redirect('ms-settings:wifi-provisioning?uri=' . $urlGenerator->generate('profile_windows_serve', ['uuid' => $uuid], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    #[Route('/profile/windows_serve', name: 'profile_windows_serve')]
    public function profileWindowsServe(Request $request): Response
    {
        $cache = new CacheUtils();
        $profileData = $cache->read('profile_' . $request->query->get("uuid"));
        if (!$profileData) {
            throw new RuntimeException("Profile not found");
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

        return sprintf($format,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), // 8 hex characters
            mt_rand(0, 0xffff), // 4 hex characters
            mt_rand(0, 0x0fff) | 0x4000, // 4 hex characters, 13th bit set to 0100 (version 4 UUID)
            mt_rand(0, 0x3fff) | 0x8000, // 4 hex characters, 17th bit set to 1000 (variant 1 UUID)
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff) // 12 hex characters
        );
    }

    private function createOrUpdateRadiusUser($user, RadiusUserRepository $radiusUserRepository, UserRepository $userRepository): RadiusUser
    {
        $radiusUser = $radiusUserRepository->findOneBy([
            'username' => $user->getUserIdentifier() . "@" . $this->getParameter('app.radius_realm')
        ]);

        if (!$radiusUser) {
            $user->setRadiusToken($this->generateToken());
            $userRepository->save($user, true);

            $radiusUser = new RadiusUser();
            $radiusUser->setUsername($user->getUserIdentifier() . "@" . $this->getParameter('app.radius_realm'));
            $radiusUser->setAttribute('Cleartext-Password');
            $radiusUser->setOp(':=');
            $radiusUser->setValue($user->getRadiusToken());
            $radiusUserRepository->save($radiusUser, true);
        }

        return $radiusUser;
    }

}
