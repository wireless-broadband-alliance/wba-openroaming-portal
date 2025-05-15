<?php

namespace App\Controller;

use App\Entity\Setting;
use App\Entity\TextEditor;
use App\Enum\TextEditorName;
use App\Enum\TextInputType;
use App\Service\GetSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TOSController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings
    ) {
    }

    #[Route('/terms-conditions', name: 'app_terms_conditions')]
    public function termsConditions(EntityManagerInterface $em): RedirectResponse|Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        $settingsRepository = $em->getRepository(Setting::class);
        $tosFormat = $settingsRepository->findOneBy(['name' => 'TOS']);
        $textEditorRepository = $em->getRepository(TextEditor::class);

        if (
            $tosFormat &&
            $tosFormat->getValue() === TextInputType::TEXT_EDITOR->value
        ) {
            if ($textEditorRepository->findOneBy(['name' => TextEditorName::TOS->value]) !== null) {
                $content = $textEditorRepository->findOneBy(['name' => TextEditorName::TOS->value])->getContent();
            } else {
                $content = '';
            }
            return $this->render('landing/shared/tos/_tos.html.twig', [
                'content' => $content,
                'data' => $data
            ]);
        }

        if (
            $tosFormat &&
            $tosFormat->getValue() === TextInputType::LINK->value &&
            $settingsRepository->findOneBy(['name' => 'TOS_LINK'])
        ) {
            return $this->redirect($settingsRepository->findOneBy(['name' => 'TOS_LINK'])->getValue());
        }

        return $this->redirectToRoute('app_landing');
    }

    #[Route('/privacy-policy', name: 'app_privacy_policy')]
    public function privacyPolicy(EntityManagerInterface $em): RedirectResponse|Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        $settingsRepository = $em->getRepository(Setting::class);
        $textEditorRepository = $em->getRepository(TextEditor::class);
        $privacyPolicyFormat = $settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY']);

        if (
            $privacyPolicyFormat &&
            $privacyPolicyFormat->getValue() === TextInputType::TEXT_EDITOR->value
        ) {
            if ($textEditorRepository->findOneBy(['name' => TextEditorName::PRIVACY_POLICY->value]) !== null) {
                $content = $textEditorRepository->findOneBy(
                    ['name' => TextEditorName::PRIVACY_POLICY->value]
                )->getContent();
            } else {
                $content = '';
            }
            return $this->render('landing/shared/tos/_privacy_policy.html.twig', [
                'content' => $content,
                'data' => $data
            ]);
        }

        if (
            $privacyPolicyFormat &&
            $privacyPolicyFormat->getValue() === TextInputType::LINK->value &&
            $settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK'])
        ) {
            return $this->redirect($settingsRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK'])->getValue());
        }

        return $this->redirectToRoute('app_landing');
    }
}
