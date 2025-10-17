<?php

namespace App\Service;

use App\Entity\InstallationProgress;
use App\Enum\InstallationProgressType;
use App\Enum\InstallationStep;
use App\Enum\SettingName;
use App\Repository\InstallationProgressRepository;
use App\Repository\SettingRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class InstallationService
{
    public function __construct(
        private readonly InstallationProgressRepository $installationProgressRepository,
        private SettingRepository $settingRepository,
        private ParameterBagInterface $parameterBag,
        private MailerInterface $mailer,
    ) {
    }

    public function lastInstallation(): ?InstallationProgress
    {
        $lastInstallation = $this->installationProgressRepository->getLast();

        if ($lastInstallation instanceof InstallationProgress) {
            if (
                $lastInstallation->getInstallationState() === InstallationProgressType::COMPLETED->value ||
                $lastInstallation->getInstallationState() === InstallationProgressType::ABORTED->value
            ) {
                return null;
            }
            return $lastInstallation;
        }
        return $lastInstallation;
    }

    public function getStep(InstallationProgress $installationProgress): string
    {
        if (
            $installationProgress->getDbOpenRoaming() &&
            $installationProgress->getDbFreeradius()
        ) {
            if (
                $installationProgress->getTurnstileKey() &&
                $installationProgress->getTurnstileSecret() &&
                $installationProgress->getTrustedProxies()
            ) {
                if (
                    $installationProgress->getEmailAdmin() &&
                    $installationProgress->getPasswordAdmin() &&
                    $installationProgress->getAdminConfirmation()
                ) {
                    return InstallationStep::COMPLETED->value;
                }
                return InstallationStep::ADMIN->value;
            }
            return InstallationStep::SETTINGS->value;
        }
        return InstallationStep::DATABASE->value;
    }

    public function sendAdminConfirmationCode(InstallationProgress $installationProgress): void
    {
        $verificationCode = random_int(100000, 999999);
        $installationProgress->setConfirmCodeAdmin($verificationCode);
        $installationProgress->setUpdatedAt(new \DateTime());

        $emailTitle = $this->settingRepository->findOneBy(['name' => SettingName::PAGE_TITLE->value])->getValue();
        $contactEmail = $this->settingRepository->findOneBy([
            'name' => SettingName::CONTACT_EMAIL->value
        ])->getValue();
        $supportTeam = $this->settingRepository->findOneBy(['name' => SettingName::PAGE_TITLE->value])->getValue();
        $customerLogo = $this->settingRepository->findOneBy([
            'name' => SettingName::CUSTOMER_LOGO->value
        ])->getValue();
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $logoPath = $projectDir . '/public' . $customerLogo;

        $email = new TemplatedEmail()
            ->from(
                new Address(
                    $this->parameterBag->get('app.email_address'),
                    $this->parameterBag->get('app.sender_name')
                )
            )
            ->to($installationProgress->getEmailAdmin())
            ->subject('Confirm Code')
            ->htmlTemplate('email/confirmation_code.html.twig')
            ->context([
                'uuid' => $installationProgress->getEmailAdmin(),
                'emailTitle' => $emailTitle,
                'contactEmail' => $contactEmail,
                'twoFaCode' => $verificationCode,
            ])
            ->embedFromPath($logoPath, 'logo_cid');

        $this->mailer->send($email);
    }
}
