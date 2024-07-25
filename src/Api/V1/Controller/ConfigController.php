<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Repository\SettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ConfigController extends AbstractController
{
    private SettingRepository $settingRepository;
    private ParameterBagInterface $parameterBag;

    public function __construct(SettingRepository $settingRepository, ParameterBagInterface $parameterBag)
    {
        $this->settingRepository = $settingRepository;
        $this->parameterBag = $parameterBag;
    }

    public function __invoke(): JsonResponse
    {
        // The included settings
        $includedNamesSetting = [
            'PLATFORM_MODE',
            'USER_VERIFICATION',
            'TURNSTILE_CHECKER',
            'CONTACT_EMAIL',
            'ALL_AUTHS_JUST_ENABLED_VALUES',
            'TOS_LINK',
            'PRIVACY_POLICY_LINK',
        ];

        // Envs variables
        $includedNamesEnvs = [
            'TURNSTILE_KEY' => $this->parameterBag->get('app.turnstile_key'),
        ];

        $settings = $this->settingRepository->findAllIn($includedNamesSetting);
        $content = [];

        // Map the settings into a single associative array
        foreach ($settings as $setting) {
            $content[$setting->getName()] = $setting->getValue();
        }

        // Add the environmental settings to the content
        foreach ($includedNamesEnvs as $name => $value) {
            $content[$name] = $value;
        }

        // Create the response
        return (new BaseResponse(200, $content))->toResponse();
    }
}
