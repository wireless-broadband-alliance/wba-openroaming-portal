<?php

namespace App\Command;

use App\Entity\Notification;
use App\Enum\AnalyticalEventType;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Service\CertificateCheckerService;
use App\Service\EmailGenerator;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

#[AsCommand(
    name: 'notify:superAdminWhenCertsExpires',
    description: 'Notify super Admin when certificates are about to expire',
)]

class NotifySuperAdminWhenCertsExpiresCommand extends Command
{
    public function __construct(
        private readonly CertificateCheckerService $certificateService,
        private readonly ParameterBagInterface $parameterBag,
        private readonly EmailGenerator $emailGenerator,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository,
    ) {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    public function notifySuperAdminWhenCertsExpires(
        OutputInterface $output): void
    {
        $certificatePath = $this->parameterBag->get('kernel.project_dir') . '/signing-keys/cert.pem';
        $certificateLimitDate = strtotime(
            (string)$this->certificateService->getCertificateExpirationDate($certificatePath)
        );
        $realTime = time();
        $timeLeft = round(($certificateLimitDate - $realTime) / (86400)) - 1;
        $certLimitDate = ((int)$timeLeft);

        if ($certLimitDate < 30) {
            try {
                $user = $this->userRepository->findSuperAdmin();
                if ($user) {
                    if ($certLimitDate < 0) {
                        $lastNotification = $this->notificationRepository->findLastNotificationByType($user, AnalyticalEventType::NOTIFY_ADMIN_EXPIRED_CERT->value);
                        $now = new DateTime();
                        $limit = $now->modify('-5 days'); // TODO ask about this time interval!!
                        if (!$lastNotification || $lastNotification->getLastNotification() < $limit) {
                            $this->emailGenerator->sendNotifyExpiredCertEmail($user, $certLimitDate);
                            $notification = new Notification();
                            $notification->setUser($user);
                            $notification->setLastNotification(new DateTime());
                            $notification->setType(AnalyticalEventType::NOTIFY_ADMIN_EXPIRED_CERT->value);
                            $this->entityManager->persist($notification);
                            $this->entityManager->flush();
                        }
                    } else {
                        $lastNotification = $this->notificationRepository->findLastNotificationByType($user, AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT->value);
                        $now = new DateTime();
                        $limit = $now->modify('-31 days'); // TODO ask about this time interval!!
                        if (!$lastNotification || $lastNotification->getLastNotification() < $limit) {
                            $this->emailGenerator->sendNotifyExpiresCertEmail($user, $certLimitDate);
                            $notification = new Notification();
                            $notification->setUser($user);
                            $notification->setLastNotification(new DateTime());
                            $notification->setType(AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT->value);
                            $this->entityManager->persist($notification);
                            $this->entityManager->flush();
                        }
                    }
                }
            } catch (TransportExceptionInterface $e) {
                $output->writeln(
                    "Failed to notify the admin: " . $e->getMessage()
                );
            }

        }
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->notifySuperAdminWhenCertsExpires($output);
        $output->writeln('Super Admin Notified');

        return Command::SUCCESS;
    }
}