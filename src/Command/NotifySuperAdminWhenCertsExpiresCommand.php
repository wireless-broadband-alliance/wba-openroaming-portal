<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Service\CertificateCheckerService;
use App\Service\EmailGenerator;
use App\Service\NotificationService;
use DateTime;
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
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationService $notificationService,
    ) {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    public function notifySuperAdminWhenCertsExpires(
        OutputInterface $output
    ): void {
        $certificatePath = $this->parameterBag->get('kernel.project_dir') . '/signing-keys/cert.pem';
        $certificateLimitDate = strtotime(
            (string)$this->certificateService->getCertificateExpirationDate($certificatePath)
        );
        $realTime = time();
        $timeLeft = round(($certificateLimitDate - $realTime) / (86400)) - 1;
        $certLimitDate = ((int)$timeLeft);

        if ($certLimitDate < 91) {
            try {
                $user = $this->userRepository->findSuperAdmin();
                if ($user instanceof User) {
                    if ($certLimitDate < 0) {
                        $lastNotification = $this
                            ->notificationRepository
                            ->findLastNotificationByType(
                                $user,
                                AnalyticalEventType::NOTIFY_ADMIN_EXPIRED_CERT->value
                            );
                        $now = new DateTime();
                        $limit = $now->modify('-7 days');
                        if (!$lastNotification || $lastNotification->getLastNotification() < $limit) {
                            $this->emailGenerator->sendNotifyExpiredCertEmail($user);
                            $this->notificationService->createNotification(
                                $user,
                                AnalyticalEventType::NOTIFY_ADMIN_EXPIRED_CERT->value
                            );
                        }
                    } elseif ($certLimitDate < 1) {
                        $lastNotification = $this->notificationRepository->findLastNotificationByType(
                            $user,
                            AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT_ONE_DAY->value
                        );
                        $now = new DateTime();
                        $limitTreeDays = $now->modify('-1 days');
                        if (!$lastNotification || $lastNotification->getLastNotification() < $limitTreeDays) {
                            $this->emailGenerator->sendNotifyExpiresCertEmail($user, $certLimitDate);
                            $this->notificationService->createNotification(
                                $user,
                                AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT_ONE_DAY->value
                            );
                        }
                    } elseif ($certLimitDate < 3) {
                        $lastNotification = $this->notificationRepository->findLastNotificationByType(
                            $user,
                            AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT_THREE_DAYS->value
                        );
                        $now = new DateTime();
                        $limitTreeDays = $now->modify('-3 days');
                        if (!$lastNotification || $lastNotification->getLastNotification() < $limitTreeDays) {
                            $this->emailGenerator->sendNotifyExpiresCertEmail($user, $certLimitDate);
                            $this->notificationService->createNotification(
                                $user,
                                AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT_THREE_DAYS->value
                            );
                        }
                    } elseif ($certLimitDate < 7) {
                        $lastNotification = $this->notificationRepository->findLastNotificationByType(
                            $user,
                            AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT_WEEK->value
                        );
                        $now = new DateTime();
                        $limitWeek = $now->modify('-7 days');
                        if (!$lastNotification || $lastNotification->getLastNotification() < $limitWeek) {
                            $this->emailGenerator->sendNotifyExpiresCertEmail($user, $certLimitDate);
                            $this->notificationService->createNotification(
                                $user,
                                AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT_WEEK->value
                            );
                        }
                    } elseif ($certLimitDate < 30) {
                        $lastNotification = $this->notificationRepository->findLastNotificationByType(
                            $user,
                            AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT_MONTH->value
                        );
                        $now = new DateTime();
                        $limitMonth = $now->modify('-30 days');
                        if (!$lastNotification || $lastNotification->getLastNotification() < $limitMonth) {
                            $this->emailGenerator->sendNotifyExpiresCertEmail($user, $certLimitDate);
                            $this->notificationService->createNotification(
                                $user,
                                AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT_MONTH->value
                            );
                        }
                    } elseif ($certLimitDate < 60) {
                        $lastNotification = $this->notificationRepository->findLastNotificationByType(
                            $user,
                            AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT_TWO_MONTHS->value
                        );
                        $now = new DateTime();
                        $limitMonth = $now->modify('-60 days');
                        if (!$lastNotification || $lastNotification->getLastNotification() < $limitMonth) {
                            $this->emailGenerator->sendNotifyExpiresCertEmail($user, $certLimitDate);
                            $this->notificationService->createNotification(
                                $user,
                                AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT_TWO_MONTHS->value
                            );
                        }
                    } elseif ($certLimitDate < 90) {
                        $lastNotification = $this->notificationRepository->findLastNotificationByType(
                            $user,
                            AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT_THREE_MONTHS->value
                        );
                        $now = new DateTime();
                        $limitMonth = $now->modify('-90 days');
                        if (!$lastNotification || $lastNotification->getLastNotification() < $limitMonth) {
                            $this->emailGenerator->sendNotifyExpiresCertEmail($user, $certLimitDate);
                            $this->notificationService->createNotification(
                                $user,
                                AnalyticalEventType::NOTIFY_ADMIN_EXPIRING_CERT_THREE_MONTHS->value
                            );
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
