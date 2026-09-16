<?php

namespace App\Controller;

use App\DTO\SMSProviderDTO;
use App\Entity\Setting;
use App\Entity\SMSProvider;
use App\Entity\SMSProviderParam;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\ParamType;
use App\Enum\SettingName;
use App\Enum\SMSProviderType;
use App\Form\SMSProviderType as SMSProviderFormType;
use App\Repository\SettingRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[IsGranted(UserAuthenticationVoter::SMS_CONFIG_READ)]
class SMSProviderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly GetSettings $getSettings,
        private readonly SettingRepository $settingRepository,
        private readonly EventActions $eventActions,
    ) {
    }

    #[Route('/dashboard/settings/sms/providers', name: 'admin_dashboard_settings_sms_providers', methods: ['GET'])]
    public function index(): Response
    {
        $data = $this->getSettings->getSettings();
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        return $this->render(
            'dashboard/shared/settings_actions/sms_providers/index.html.twig',
            [
                'data' => $data,
                'currentUser' => $currentUser,
            ]
        );
    }

    #[Route(
        '/dashboard/settings/sms/providers/new',
        name: 'admin_dashboard_settings_sms_providers_new',
        methods: ['GET', 'POST']
    )]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function new(Request $request): Response
    {
        $dto = new SMSProviderDTO();
        $form = $this->createForm(SMSProviderFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $currentUser */
            $currentUser = $this->getUser();

            $provider = $this->createProviderFromDto($dto);
            $this->entityManager->flush();

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SMS_PROVIDER_CREATED->value,
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $request->getClientIp(),
                    EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                    EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                    EventMetadataKeysType::OLD_DATA->value => null,
                    EventMetadataKeysType::NEW_DATA->value => $provider->getName(),
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('SMSProviderSavedSuccessfully', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
        }

        return $this->render('dashboard/shared/settings_actions/sms_providers/form.html.twig', [
            'form' => $form->createView(),
            'provider' => null,
            'data' => $this->getSettings->getSettings(),
        ]);
    }

    #[Route(
        '/dashboard/settings/sms/providers/{id}/edit',
        name: 'admin_dashboard_settings_sms_providers_edit',
        methods: ['GET', 'POST']
    )]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function edit(SMSProvider $provider, Request $request): Response
    {
        $dto = SMSProviderDTO::fromEntity($provider);
        $form = $this->createForm(SMSProviderFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $currentUser */
            $currentUser = $this->getUser();

            // Capture before mutating, same reasoning as delete() — the entity's
            // old state is gone once updateProviderFromDto() overwrites it
            $previousName = $provider->getName();
            $previousTestMode = $provider->isTestMode();

            $this->updateProviderFromDto($provider, $dto);
            $this->entityManager->flush();

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SMS_PROVIDER_UPDATED->value,
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $request->getClientIp(),
                    EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                    EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                    EventMetadataKeysType::OLD_DATA->value => sprintf(
                        '%s (test mode: %s)',
                        $previousName,
                        $previousTestMode ? 'yes' : 'no'
                    ),
                    EventMetadataKeysType::NEW_DATA->value => sprintf(
                        '%s (test mode: %s)',
                        $provider->getName(),
                        $provider->isTestMode() ? 'yes' : 'no'
                    ),
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('SMSProviderSavedSuccessfully', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
        }

        return $this->render('dashboard/shared/settings_actions/sms_providers/form.html.twig', [
            'form' => $form->createView(),
            'provider' => $provider,
            'data' => $this->getSettings->getSettings(),
        ]);
    }

    #[Route(
        '/dashboard/settings/sms/providers/{id}/activate',
        name: 'admin_dashboard_settings_sms_providers_activate',
        methods: ['POST']
    )]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function activate(SMSProvider $provider, Request $request): Response
    {
        $token = $request->request->get('_token');

        if (
            !$this->isCsrfTokenValid(
                'sms-provider-activate-' . $provider->getId(),
                is_string($token) ? $token : null
            )
        ) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $setting = $this->settingRepository->findOneBy(['name' => SettingName::SMS_ACTIVE_PROVIDER->value]);
        $previousActiveProviderName = $setting?->getValue();

        if ($setting === null) {
            $setting = new Setting();
            $setting->setName(SettingName::SMS_ACTIVE_PROVIDER->value);
            $this->entityManager->persist($setting);
        }

        $setting->setValue($provider->getName());
        $this->entityManager->flush();

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::SMS_PROVIDER_ACTIVATED->value,
            new DateTime(),
            [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                EventMetadataKeysType::OLD_DATA->value => $previousActiveProviderName,
                EventMetadataKeysType::NEW_DATA->value => $provider->getName(),
            ]
        );

        $this->addFlash(
            'success',
            $this->translator->trans('SMSProviderActivatedSuccessfully', [], 'controllers')
        );

        return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
    }

    #[Route(
        '/dashboard/settings/sms/providers/{id}/deactivate',
        name: 'admin_dashboard_settings_sms_providers_deactivate',
        methods: ['POST']
    )]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function deactivate(SMSProvider $provider, Request $request): Response
    {
        $token = $request->request->get('_token');

        if (
            !$this->isCsrfTokenValid(
                'sms-provider-deactivate-' . $provider->getId(),
                is_string($token) ? $token : null
            )
        ) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $setting = $this->settingRepository->findOneBy(['name' => SettingName::SMS_ACTIVE_PROVIDER->value]);
        $activeProviderName = $setting?->getValue();

        if ($setting === null || $activeProviderName !== $provider->getName()) {
            $this->addFlash(
                'error',
                $this->translator->trans('ProviderIsNotActive', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
        }

        $setting->setValue(null);
        $this->entityManager->flush();

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::SMS_PROVIDER_DEACTIVATED->value,
            new DateTime(),
            [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                EventMetadataKeysType::OLD_DATA->value => $provider->getName(),
                EventMetadataKeysType::NEW_DATA->value => null,
            ]
        );

        $this->addFlash(
            'success',
            $this->translator->trans('SMSProviderDeactivatedSuccessfully', [], 'controllers')
        );

        return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
    }

    #[Route(
        '/dashboard/settings/sms/providers/{id}/delete',
        name: 'admin_dashboard_settings_sms_providers_delete',
        methods: ['POST']
    )]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function delete(SMSProvider $provider, Request $request): Response
    {
        $token = $request->request->get('_token');

        if (
            !$this->isCsrfTokenValid(
                'sms-provider-delete-' .
                $provider->getId(),
                is_string($token) ? $token : null
            )
        ) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $activeProviderName = $this->settingRepository
            ->findOneBy(['name' => SettingName::SMS_ACTIVE_PROVIDER->value])
            ?->getValue();

        if ($activeProviderName !== null && $activeProviderName === $provider->getName()) {
            $this->addFlash(
                'error',
                $this->translator->trans('cannotDeleteActiveSMSProvider', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
        }

        $deletedProviderName = $provider->getName();

        $this->entityManager->remove($provider);
        $this->entityManager->flush();

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::SMS_PROVIDER_DELETED->value,
            new DateTime(),
            [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                EventMetadataKeysType::OLD_DATA->value => $deletedProviderName,
                EventMetadataKeysType::NEW_DATA->value => null,
            ]
        );

        $this->addFlash(
            'success',
            $this->translator->trans('SMSProviderDeletedSuccessfully', [], 'controllers')
        );

        return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
    }

    /**
     * Builds and persists a brand-new SMSProvider from the DTO's named fields.
     */
    private function createProviderFromDto(SMSProviderDTO $dto): SMSProvider
    {
        $now = new DateTimeImmutable();

        $provider = new SMSProvider();
        $provider->setCreatedAt($now);
        $provider->setUpdatedAt($now);
        $provider->setName((string) $dto->name);
        $provider->setSMSProviderType($dto->smsProviderType);
        $provider->setTestMode($dto->testMode);
        $this->entityManager->persist($provider);

        foreach ($this->buildParamValues($dto) as $paramType => $value) {
            $param = new SMSProviderParam();
            $param->setCreatedAt($now);
            $param->setUpdatedAt($now);
            $param->setType(ParamType::STRING);
            $param->setParamType($paramType);
            $param->setValue($value);
            $provider->addSmsProviderParam($param);
            $this->entityManager->persist($param);
        }

        return $provider;
    }

    /**
     * Updates an existing SMSProvider's named fields, matched by paramType.
     * If the provider type itself changed, any params belonging to the old
     * type are removed since they no longer apply.
     */
    private function updateProviderFromDto(SMSProvider $provider, SMSProviderDTO $dto): void
    {
        $now = new DateTimeImmutable();

        $provider->setName((string) $dto->name);
        $provider->setSMSProviderType($dto->smsProviderType);
        $provider->setTestMode($dto->testMode);
        $provider->setUpdatedAt($now);

        $existingParamsByType = [];
        foreach ($provider->getSmsProviderParams() as $existingParam) {
            $existingParamsByType[$existingParam->getParamType()] = $existingParam;
        }

        foreach ($this->buildParamValues($dto) as $paramType => $value) {
            if (isset($existingParamsByType[$paramType])) {
                $existingParamsByType[$paramType]->setValue($value);
                $existingParamsByType[$paramType]->setUpdatedAt($now);
                unset($existingParamsByType[$paramType]);

                continue;
            }

            $param = new SMSProviderParam();
            $param->setCreatedAt($now);
            $param->setUpdatedAt($now);
            $param->setType(ParamType::STRING);
            $param->setParamType($paramType);
            $param->setValue($value);
            $provider->addSmsProviderParam($param);
            $this->entityManager->persist($param);
        }

        foreach ($existingParamsByType as $staleParam) {
            $provider->removeSmsProviderParam($staleParam);
            $this->entityManager->remove($staleParam);
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildParamValues(SMSProviderDTO $dto): array
    {
        return match ($dto->smsProviderType) {
            SMSProviderType::BUDGET_SMS => [
                'username' => (string)$dto->username,
                'userid' => (string)$dto->userid,
                'handle' => (string)$dto->handle,
                'from' => (string)$dto->from,
            ],
            default => [],
        };
    }
}
