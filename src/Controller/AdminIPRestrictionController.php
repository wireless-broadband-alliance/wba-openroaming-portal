<?php

namespace App\Controller;

use App\DTO\IpRestrictionSettingsDTO;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Form\IpRestrictionSettingsType;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\SettingsService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\IpUtils;
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
    ) {
    }

    /**
     * @throws \JsonException
     */
    #[Route('/dashboard/settings/ip-restriction', name: 'admin_dashboard_settings_ip_restriction')]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
    public function settingsSecurity(Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $dto = new IpRestrictionSettingsDTO($data);
        $form = $this->createForm(IpRestrictionSettingsType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var IpRestrictionSettingsDTO $dto */
            $dto = $form->getData();
            $allowedIps = $dto->getAllowedIps();
            $clientIp = (string)$request->getClientIp();

            // Expand local loopback check to handle IPv4 + IPv6 localhost seamlessly
            $checkIps = $allowedIps;
            if (
                in_array(
                    '127.0.0.1',
                    $allowedIps,
                    true
                ) && !in_array('::1', $allowedIps, true)
            ) {
                $checkIps[] = '::1';
            }

            // Lockout prevention check
            if ($allowedIps !== [] && !IpUtils::checkIp($clientIp, $checkIps)) {
                $errorMsg = $this->translator->trans('ipRestrictionLockout', ['%ip%' => $clientIp], 'controllers');

                // Show flash message if your layout uses flash banners
                $this->addFlash('error', $errorMsg);

                // Attach to field so it shows right under the IP inputs
                $form->get('allowedIps')->addError(new FormError($errorMsg));
            } else {
                $changeset = $this->settingsService->updateSettingsFromArray($dto->toArray());
                $this->settingsService->flush();

                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::SETTING_IP_RESTRICTION_CONF_REQUEST->value,
                    new DateTime(),
                    [
                        EventMetadataKeysType::IP->value => $clientIp,
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
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'ipRestrictionSettingsDTO' => $dto,
            'data' => $data,
            'user' => $currentUser,
        ]);
    }
}
