<?php

namespace App\Service;

use App\Enum\OSTypes;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class GetSettings
{
    private function detectDevice($userAgent)
    {
        $os = OSTypes::NONE;

        // Windows
        if (preg_match('/windows|win32/i', $userAgent)) {
            $os = OSTypes::WINDOWS;
        }

        // macOS
        if (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $os = OSTypes::MACOS;
        }

        // iOS
        if (preg_match('/iphone|ipod|ipad/i', $userAgent)) {
            $os = OSTypes::IOS;
        }

        // Android
        if (preg_match('/android/i', $userAgent)) {
            $os = OSTypes::ANDROID;
        }

        // Linux
//        if (preg_match('/linux/i', $userAgent)) {
//            $os = OSTypes::LINUX;
//        }

        return $os;
    }

    public function getSettings(UserRepository $userRepository, SettingRepository $settingRepository, Request $request, RequestStack $requestStack,): array
    {
        $data = [];

        // Branding
        $data['title'] = $settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();
        $data['customerLogoName'] = $settingRepository->findOneBy(['name' => 'CUSTOMER_LOGO'])->getValue();
        $data['openroamingLogoName'] = $settingRepository->findOneBy(['name' => 'OPENROAMING_LOGO'])->getValue();
        $data['wallpaperImageName'] = $settingRepository->findOneBy(['name' => 'WALLPAPER_IMAGE'])->getValue();
        $data['welcomeText'] = $settingRepository->findOneBy(['name' => 'WELCOME_TEXT'])->getValue();
        $data['welcomeDescription'] = $settingRepository->findOneBy(['name' => 'WELCOME_DESCRIPTION'])->getValue();
        $data['contactEmail'] = $settingRepository->findOneBy(['name' => 'CONTACT_EMAIL'])->getValue();
        $data['ADDITIONAL_LABEL'] = $settingRepository->findOneBy(['name' => 'ADDITIONAL_LABEL'])->getValue();
// Demo Mode
        $data['demoMode'] = $settingRepository->findOneBy(['name' => 'DEMO_MODE'])->getValue() === 'true';
        $demoWhiteLabelSetting = $settingRepository->findOneBy(['name' => 'DEMO_WHITE_LABEL']);
        if ($demoWhiteLabelSetting !== null) {
            $data['demoModeWhiteLabel'] = $demoWhiteLabelSetting->getValue();
        }
// Auth Providers
// SAML
        $data['SAML_ENABLED'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SAML_ENABLED'])->getValue() === 'true';
        $data['SAML_LABEL'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SAML_LABEL'])->getValue();
        $data['SAML_DESCRIPTION'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SAML_DESCRIPTION'])->getValue();
// GOOGLE
        $data['GOOGLE_LOGIN_ENABLED'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_GOOGLE_LOGIN_ENABLED'])->getValue() === 'true';
        $data['GOOGLE_LOGIN_LABEL'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_GOOGLE_LOGIN_LABEL'])->getValue();
        $data['GOOGLE_LOGIN_DESCRIPTION'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION'])->getValue();
//REGISTER/LOGIN TRADITIONAL
        $data['REGISTER_ENABLED'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_REGISTER_METHOD_ENABLED'])->getValue() === 'true';
        $data['REGISTER_LABEL'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_REGISTER_METHOD_LABEL'])->getValue();
        $data['REGISTER_DESCRIPTION'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_REGISTER_METHOD_DESCRIPTION'])->getValue();
        $data['LOGIN_TRADITIONAL_ENABLED'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_METHOD_ENABLED'])->getValue() === 'true';
        $data['LOGIN_TRADITIONAL_LABEL'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_METHOD_LABEL'])->getValue();
        $data['LOGIN_TRADITIONAL_DESCRIPTION'] = $settingRepository->findOneBy(['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_METHOD_DESCRIPTION'])->getValue();

// Legal Stuff
        $data['TOS_LINK'] = $settingRepository->findOneBy(['name' => 'TOS_LINK'])->getValue();
        $data['PRIVACY_POLICY_LINK'] = $settingRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK'])->getValue();
/// Verification Form
        $data['code'] = ($user = $userRepository->findOneBy(['verificationCode' => null])) ? $user->getVerificationCode() : null;
        $data['VERIFICATION_FORM'] = false;
/// Type of Encryption for profiles
        $data['PROFILES_ENCRYPTION_TYPE_IOS_ONLY'] = $settingRepository->findOneBy(['name' => 'PROFILES_ENCRYPTION_TYPE_IOS_ONLY'])->getValue();

///
        $userAgent = $request->headers->get('User-Agent');
        $actionName = $requestStack->getCurrentRequest()->attributes->get('_route');
        $data['os'] = [
            'selected' => $payload['radio-os'] ?? $this->detectDevice($userAgent),
            'items' => [
                OSTypes::WINDOWS => ['alt' => 'Windows Logo'],
                OSTypes::IOS => ['alt' => 'Apple Logo'],
                OSTypes::ANDROID => ['alt' => 'Android Logo']
            ]
        ];

        return $data;
    }

}
