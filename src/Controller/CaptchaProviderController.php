<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CaptchaDTO;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Form\CaptchaStepType;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\SettingsService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class CaptchaProviderController extends AbstractController
{

    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator,
        private readonly SettingsService $settingsService,
        private readonly EventActions $eventActions,
    ) {
    }
    #[Route('/dashboard/settings/captchaProvider', name: 'admin_dashboard_settings_captchaProvider')]
    #[IsGranted(UserAuthenticationVoter::CAPTCHA_PROVIDER_READ)]
    public function captchaProvider(Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::CAPTCHA_PROVIDER_WRITE);

        // Initialize DTO from settings
        $dto = new CaptchaDTO($data);

        // Create form bound to DTO
        $form = $this->createForm(CaptchaStepType::class, $dto, ['disabled' => !$canWrite, 'allow_off' => true,]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $canWrite) {
            // Save updated settings
            $changeset = $this->settingsService->updateSettingsFromArray($dto->toArray());
            $this->settingsService->flush();

            // Log the event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::CAPTCHA_PROVIDER_REQUEST->value,
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $request->getClientIp(),
                    EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                    EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                    EventMetadataKeysType::CHANGESET->value  => $changeset,
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('newChangesAppliedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_captchaProvider');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'formDTO' => $dto,
            'data' => $data,
            'user' => $currentUser,
        ]);

    }
}
