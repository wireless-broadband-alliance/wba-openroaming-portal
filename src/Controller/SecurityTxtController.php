<?php

namespace App\Controller;

use App\DTO\SecurityTxtSettingsDTO;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\SettingName;
use App\Form\SecurityTxtSettingsType;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\SettingsService;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class SecurityTxtController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator,
        private readonly SettingsService $settingsService,
        private readonly EventActions $eventActions,
    ) {
    }

    #[Route('/.well-known/security.txt', name: 'app_security_txt', methods: ['GET'])]
    public function securityTxtDisplay(Request $request): Response
    {
        $settings = $this->getSettings->getSpecificSettings([
            SettingName::SECURITY_CONTACT->value,
            SettingName::SECURITY_EXPIRES->value,
            SettingName::SECURITY_PGP_FINGERPRINT->value,
        ]);

        $contact = $this->getSettingValue(
            $settings,
            SettingName::SECURITY_CONTACT
        );

        $expires = $this->getSettingValue(
            $settings,
            SettingName::SECURITY_EXPIRES
        );

        $fingerprint = $this->getSettingValue(
            $settings,
            SettingName::SECURITY_PGP_FINGERPRINT
        );

        // Contact and Expires are mandatory in RFC 9116:
        // don't serve a broken security.txt file.
        if ($contact === null || $expires === null) {
            throw $this->createNotFoundException();
        }

        // Fallback for legacy database records missing URI scheme
        if (!preg_match('#^(mailto:|https://|tel:)#i', $contact)) {
            $contact = 'mailto:' . $contact;
        }

        try {
            $expiresUtc = new DateTimeImmutable($expires)
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s.000\Z');
        } catch (Throwable) {
            throw $this->createNotFoundException();
        }

        $lines = [
            'Contact: ' . $contact,
            'Expires: ' . $expiresUtc,
        ];

        if ($fingerprint !== null) {
            $cleanFingerprint = preg_replace('/\s+/', '', $fingerprint);

            // RFC 9116 §2.5.4: Handle direct URIs (https://, openpgp4fpr:, dns:) vs raw 40-character hex fingerprints
            if (preg_match('#^(https://|dns:|openpgp4fpr:)#i', $cleanFingerprint)) {
                $lines[] = 'Encryption: ' . $cleanFingerprint;
            } elseif (preg_match('/^[0-9a-fa-f]{40}$/i', $cleanFingerprint)) {
                $lines[] = 'Encryption: openpgp4fpr:' . strtoupper($cleanFingerprint);
            }
        }

        $lines[] = 'Canonical: ' . $request->getSchemeAndHttpHost() . '/.well-known/security.txt';

        return new Response(
            implode("\n", $lines) . "\n",
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'public, max-age=86400, s-maxage=86400',
            ]
        );
    }

    /**
     * @param array<string, array{value: string}> $settings
     */
    private function getSettingValue(
        array $settings,
        SettingName $settingName,
    ): ?string {
        $value = $settings[$settingName->value]['value'] ?? null;

        return $value === null || trim($value) === ''
            ? null
            : trim($value);
    }

    #[Route('/dashboard/settings/security-txt', name: 'admin_dashboard_settings_security_txt')]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
    public function settingsSecurity(Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(AdminRoleType::ROLE_SUPER_ADMIN->value);

        // Initialize DTO from settings
        $dto = new SecurityTxtSettingsDTO($data);

        // Create form bound to DTO
        $form = $this->createForm(SecurityTxtSettingsType::class, $dto, ['disabled' => !$canWrite]);
        $form->handleRequest($request);

        if ($canWrite && $form->isSubmitted() && $form->isValid()) {
            /** @var SecurityTxtSettingsDTO $dto */
            $dto = $form->getData();

            // Save updated settings
            $changeset = $this->settingsService->updateSettingsFromArray($dto->toArray());
            $this->settingsService->flush();

            // Log the event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_SECURITY_TXT_CONF_REQUEST->value
                ?? 'SETTING_SECURITY_TXT_CONF_REQUEST',
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

            return $this->redirectToRoute('admin_dashboard_settings_security_txt');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'securityTxtSettingsDTO' => $dto,
            'data' => $data,
            'user' => $currentUser,
        ]);
    }
}
