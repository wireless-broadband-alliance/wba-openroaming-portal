<?php

namespace App\Controller;

use App\DTO\IpRestrictionSettingsDTO;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\SettingsConfigType;
use App\Form\IpRestrictionSettingsType;
use App\Service\DatabaseConnectionService;
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

class AdminIPRestrictionController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator,
        private readonly SettingsService $settingsService,
        private readonly EventActions $eventActions,
        private readonly DatabaseConnectionService $databaseConnectionService,
    ) {
    }

    #[Route('/dashboard/settings/ip-restriction', name: 'admin_dashboard_settings_ip_restriction')]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
    public function settingsSecurity(Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(AdminRoleType::ROLE_SUPER_ADMIN->value);

        // Initialize DTO from settings
        $dto = new IpRestrictionSettingsDTO($data);

        // Create form bound to DTO
        $form = $this->createForm(IpRestrictionSettingsType::class, $dto, ['disabled' => !$canWrite]);
        $form->handleRequest($request);

        if ($canWrite && $form->isSubmitted() && $form->isValid()) {
            /** @var IpRestrictionSettingsDTO $dto */
            $dto = $form->getData();

            // Save updated settings in Database
            $changeset = $this->settingsService->updateSettingsFromArray($dto->toArray());
            $this->settingsService->flush();

            // Write trusted proxies / IP restrictions to .env if set
            if (!empty($dto->trustedProxies)) {
                $trustedProxiesValue = implode(',', $dto->trustedProxies);

                $this->databaseConnectionService->writeDatabaseUrlToEnv(
                    $trustedProxiesValue,
                    SettingsConfigType::TRUSTED_PROXIES->value
                );
            }

            // Log the event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_IP_RESTRICTION_CONF_REQUEST->value
                ?? 'SETTING_IP_RESTRICTION_CONF_REQUEST',
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $request->getClientIp(),
                    EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                    EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                    EventMetadataKeysType::CHANGESET->value => $changeset,
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('securityConfigurationAppliedSuccessfully', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_ip_restriction');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'ipRestrictionSettingsDTO' => $dto,
            'data' => $data,
            'user' => $currentUser,
        ]);
    }
}
