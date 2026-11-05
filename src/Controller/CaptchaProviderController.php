<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CaptchaDTO;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\CaptchaProviderEnum;
use App\Enum\EventMetadataKeysType;
use App\Form\CaptchaStepType;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\Captcha\CaptchaValidator;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\SettingsService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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
        private readonly CaptchaValidator $captchaValidator,
    ) {
    }

    #[Route('/dashboard/settings/captchaProvider', name: 'admin_dashboard_settings_captcha_provider')]
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
        $form = $this->createForm(CaptchaStepType::class, $dto, ['disabled' => !$canWrite, 'allow_off' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $canWrite) {
            $secretKey = match ($dto->provider) {
                CaptchaProviderEnum::TURNSTILE => $dto->turnstileSecret,
                CaptchaProviderEnum::RECAPTCHA3 => $dto->recaptcha3Secret,
                default => null,
            };

            $isValidCredentials = true;

            if ($dto->provider !== null && $dto->provider !== CaptchaProviderEnum::OFF) {
                $validation = $this->captchaValidator->validateCredentials($dto->provider, $secretKey);

                if (!$validation['success']) {
                    $isValidCredentials = false;
                    $errorKey = $validation['error'] ?? 'invalidCaptchaCredentials';
                    $translatedError = $this->translator->trans($errorKey, [], 'controllers');

                    $form->addError(new FormError($translatedError));

                    $this->addFlash('danger', $translatedError);
                    $this->addFlash('error', $translatedError);
                }
            }

            if ($isValidCredentials) {
                $changeset = $this->settingsService->updateSettingsFromArray($dto->toArray());
                $this->settingsService->flush();

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

                return $this->redirectToRoute('admin_dashboard_settings_captcha_provider');
            }
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'formDTO' => $dto,
            'data' => $data,
            'user' => $currentUser,
        ]);
    }
}
