<?php

namespace App\Controller;

use App\Enum\TextEditorName;
use App\Enum\TextInputType;
use App\Repository\SettingRepository;
use App\Repository\TextEditorRepository;
use App\Service\GetSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TOSController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly SettingRepository $settingRepository,
        private readonly TextEditorRepository $textEditorRepository,
    ) {
    }

    #[Route('/terms-conditions', name: 'app_terms_conditions')]
    public function termsConditions(): RedirectResponse|Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();
        $tosFormat = $this->settingRepository->findOneBy(['name' => 'TOS']);

        if (
            $tosFormat &&
            $tosFormat->getValue() === TextInputType::TEXT_EDITOR->value
        ) {
            if ($this->textEditorRepository->findOneBy(['name' => TextEditorName::TOS->value]) !== null) {
                $content = $this->textEditorRepository->findOneBy(
                    ['name' => TextEditorName::TOS->value]
                )->getContent();
            } else {
                $content = '';
            }

            return $this->render('landing/shared/tos/_tos.html.twig', [
                'content' => $content,
                'data' => $data,
            ]);
        }

        if (
            $tosFormat &&
            $tosFormat->getValue() === TextInputType::LINK->value &&
            $this->settingRepository->findOneBy(['name' => 'TOS_LINK'])
        ) {
            return $this->redirect($this->settingRepository->findOneBy(['name' => 'TOS_LINK'])->getValue());
        }

        return $this->redirectToRoute('app_landing');
    }

    #[Route('/privacy-policy', name: 'app_privacy_policy')]
    public function privacyPolicy(): RedirectResponse|Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        $privacyPolicyFormat = $this->settingRepository->findOneBy(['name' => 'PRIVACY_POLICY']);

        if (
            $privacyPolicyFormat &&
            $privacyPolicyFormat->getValue() === TextInputType::TEXT_EDITOR->value
        ) {
            if ($this->textEditorRepository->findOneBy(['name' => TextEditorName::PRIVACY_POLICY->value]) !== null) {
                $content = $this->textEditorRepository->findOneBy(
                    ['name' => TextEditorName::PRIVACY_POLICY->value]
                )->getContent();
            } else {
                $content = '';
            }

            return $this->render('landing/shared/tos/_privacy_policy.html.twig', [
                'content' => $content,
                'data' => $data,
            ]);
        }

        if (
            $privacyPolicyFormat &&
            $privacyPolicyFormat->getValue() === TextInputType::LINK->value &&
            $this->settingRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK'])
        ) {
            return $this->redirect(
                $this->settingRepository->findOneBy(
                    ['name' => 'PRIVACY_POLICY_LINK']
                )->getValue()
            );
        }

        return $this->redirectToRoute('app_landing');
    }
}
