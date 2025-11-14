<?php

namespace App\Service;

use App\DTO\InstallationProgressDTO;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\InstallationProgressType;
use App\Enum\InstallationStep;
use App\Enum\SettingName;
use App\Repository\EventRepository;
use App\Repository\InstallationProgressRepository;
use App\Repository\SettingRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class InstallationService
{
    public function __construct(
        private InstallationProgressRepository $installationProgressRepository,
        private SettingRepository $settingRepository,
        private ParameterBagInterface $parameterBag,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
        private DatabaseConnectionService $databaseConnectionService,
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
                    $installationProgress->setInstallationState(InstallationProgressType::COMPLETED->value);
                    $this->entityManager->persist($installationProgress);
                    $this->entityManager->flush();
                    return InstallationStep::COMPLETED->value;
                }
                return InstallationStep::ADMIN->value;
            }
            return InstallationStep::SETTINGS->value;
        }
        return InstallationStep::DATABASE->value;
    }

    /**
     * @throws RandomException
     * @throws TransportExceptionInterface
     */
    public function sendAdminConfirmationCode(InstallationProgress $installationProgress): void
    {
        $verificationCode = random_int(100000, 999999);
        $installationProgress->setConfirmCodeAdmin($verificationCode);
        $installationProgress->setUpdatedAt(new \DateTime());
        $this->entityManager->persist($installationProgress);
        $this->entityManager->flush();

        $emailTitle = $this->settingRepository->findOneBy(['name' => SettingName::PAGE_TITLE->value])->getValue();
        $contactEmail = $this->settingRepository->findOneBy([
            'name' => SettingName::CONTACT_EMAIL->value,
        ])->getValue();
        $customerLogo = $this->settingRepository->findOneBy([
            'name' => SettingName::CUSTOMER_LOGO->value,
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

    public function canSendCode(InstallationProgress $installationProgress, User $user): bool
    {
        $nrAttempts = $this->settingRepository->findOneBy(
            ['name' => SettingName::TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE->value]
        )->getValue();
        $timeToResetAttempts = $this->settingRepository->findOneBy(
            ['name' => SettingName::TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS->value]
        )->getValue();
        $limitTime = new DateTime();
        $limitTime->modify('-' . $timeToResetAttempts . ' minutes');
        $attempts = $this->eventRepository->find2FACodeAttemptEvent(
            $user,
            $nrAttempts,
            $limitTime,
            AnalyticalEventType::INSTALLATION_ADMIN_CONFIRM_CODE_SENT->value
        );
        return count($attempts) < $nrAttempts;
    }

    public function fillDto(
        InstallationProgress $installationProgress
    ): InstallationProgressDTO {
        $dto = new InstallationProgressDTO();
        $dto->installationState = $installationProgress->getInstallationState();

        $dbOpenRoamingPartials = $this->databaseConnectionService->parseDatabaseUrl($installationProgress->getDbOpenRoaming());
        $dto->dbOpenRoamingUserName = $dbOpenRoamingPartials['username'];
        $dto->dbOpenRoamingPassword = $dbOpenRoamingPartials['password'];
        $dto->dbOpenRoamingIp = $dbOpenRoamingPartials['host'];
        $dto->dbOpenRoamingPort = $dbOpenRoamingPartials['port'];

        $dbFreeradiusPartials = $this->databaseConnectionService->parseDatabaseUrl($installationProgress->getDbFreeradius());
        $dto->dbFreeradiusUserName = $dbFreeradiusPartials['username'];
        $dto->dbFreeradiusPassword = $dbFreeradiusPartials['password'];
        $dto->dbFreeradiusIp = $dbFreeradiusPartials['host'];
        $dto->dbFreeradiusPort = $dbFreeradiusPartials['port'];

        $dto->trustedProxies = $installationProgress->getTrustedProxies();
        $dto->turnstileKey = $installationProgress->getTurnstileKey();
        $dto->turnstileSecret = $installationProgress->getTurnstileSecret();

        $dto->emailAdmin = $installationProgress->getEmailAdmin();

        $dto->createdAt = $installationProgress->getCreatedAt();
        $dto->updatedAt = $installationProgress->getUpdatedAt();

        return $dto;
    }
}
