<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\CertificateTestResult;
use App\Enum\ProcessStatusType;
use App\Enum\SessionStatus;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final readonly class FreeradiusTestOrchestrator
{
    public function __construct(
        private FreeradiusConnectionService $freeradiusConnectionService,
        private EntityManagerInterface $entityManager,
        private EventActions $eventActions,
        private Security $security
    ) {
    }

    public function run(
        CertificateSetupProcess $process,
        string $remoteHost,
        int $remotePort,
        array $paths,
        Request $request,
    ): void {
        // Get the User
        /** @var User $user */
        $user = $this->security->getUser();

        // TLS Connection
        $result = $this->freeradiusConnectionService->connectToServer(
            $remoteHost,
            $remotePort,
            $paths['cert'],
            $paths['fullchain'],
            $paths['privkey'],
            $paths['ca']
        );

        // Validates if the chain belongs to the server
        $this->freeradiusConnectionService->validate($result['chain'], $paths);

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::CERTIFICATE_SETUP_PROCESS_FREERAEDIUS_TEST->value,
            new DateTime(),
            [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'by' => $user->getUuid(),
            ]
        );

        $session = $request->getSession();
        if ($session->has(SessionStatus::SYSTEM_RESET_REQUEST->value)) {
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::SYSTEM_RESET_REQUEST_COMPLETED->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'by' => $user->getUuid(),
                ]
            );

            // Clear all the sessions requests in case the system_reset is completed
            $session->remove(SessionStatus::SYSTEM_RESET_REQUEST->value);
            $session->remove(SessionStatus::INSTALLATION_STARTED->value);
            $session->remove(SessionStatus::CERTIFICATE_STARTED->value);
        }

        // Update DB results
        $process->setStatus(ProcessStatusType::COMPLETED);
        $process->setFreeradiusTestResult(CertificateTestResult::PASSED);
        $this->entityManager->persist($process);
        $this->entityManager->flush();
    }
}
