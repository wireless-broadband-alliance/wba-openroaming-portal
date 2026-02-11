<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Entity\InstallationProgress;
use App\Enum\ProcessStatusType;
use App\Enum\CertificateRouteAccess;
use App\Enum\CertificateTestResult;
use App\Repository\CertificateSetupProcessRepository;
use App\Repository\InstallationProgressRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

readonly class CertificateProcessCheckerService
{
    public function __construct(
        private CertificateSetupProcessRepository $certificateSetupProcessRepository,
        private CertificateCheckerService $certificateService,
        private InstallationProgressRepository $installationProgressRepository,
        private InstallationService $installationService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get the currently in-progress certificate process (if any)
     */
    public function getCurrentProcess(): ?CertificateSetupProcess
    {
        return $this->certificateSetupProcessRepository->getLatestProcess();
    }

    /**
     * Returns the stages and their completion status plus the process entity
     * @return array{
     *     active: false,
     *     stages: array<string, bool>
     * }|array{
     *     active: true,
     *     stages: array<string, bool>,
     *     process: CertificateSetupProcess
     * }
     */
    public function getProcessState(?bool $info = false): array
    {
        $process = $this->getCurrentProcess();

        if (
            $info && (!$process ||
                $process->getStatus() === ProcessStatusType::ABORTED)
        ) {
            return [
                'active' => false,
                'stages' => [],
            ];
        }
        if (
            !$info && (!$process ||
                $process->getStatus() === ProcessStatusType::ABORTED ||
                $process->getStatus() === ProcessStatusType::COMPLETED)
        ) {
                return [
                    'active' => false,
                    'stages' => [],
                ];
        }


        $stages = [];
        foreach (CertificateRouteAccess::orderedStages() as $stage) {
            $stages[$stage->value] = match ($stage) {
                CertificateRouteAccess::RADSECPROXY_UPLOAD =>
                    $process->getRadsecproxyFormCompletedAt() instanceof DateTimeImmutable,

                CertificateRouteAccess::RADSECPROXY_CONFIG =>
                    $process->getRadsecproxyConfigAppliedAt() instanceof DateTimeImmutable,

                CertificateRouteAccess::RADSECPROXY_TEST =>
                    $process->getRadsecproxyTestResult() === CertificateTestResult::PASSED,

                CertificateRouteAccess::FREERADIUS_SELECTION =>
                    $process->getFreeradiusFormCompletedAt() instanceof DateTimeImmutable,

                CertificateRouteAccess::FREERADIUS_UPLOAD =>
                    $process->getFreeradiusFormCompletedAt() instanceof DateTimeImmutable,

                CertificateRouteAccess::FREERADIUS_AUTO_RENEW =>
                    $process->getFreeradiusFormCompletedAt() instanceof DateTimeImmutable,

                CertificateRouteAccess::FREERADIUS_CONFIG =>
                    $process->getFreeradiusConfigAppliedAt() instanceof DateTimeImmutable,

                CertificateRouteAccess::FREERADIUS_TEST =>
                    $process->getFreeradiusTestResult() === CertificateTestResult::PASSED,
            };
        }

        return [
            'active' => true,
            'stages' => $stages,
            'process' => $process,
        ];
    }

    /**
     * Get Symfony route of first incomplete stage
     * @param array<string, bool> $stages
     */
    public function getNextRequiredRoute(array $stages): ?string
    {
        foreach (CertificateRouteAccess::orderedStages() as $stage) {
            if (!($stages[$stage->value] ?? false)) {
                return $stage->routeName();
            }
        }

        return null;
    }

    /**
     * Get the first incomplete stage (enum)
     */
    public function getProcessCurrentStage(): ?CertificateRouteAccess
    {
        $state = $this->getProcessState();

        /** @var array<string, bool> $stages */
        $stages = $state['stages'];

        return array_find(
            CertificateRouteAccess::orderedStages(),
            fn($stage) => !($stages[$stage->value] ?? false)
        );
    }

    /**
     * Returns index of a stage inside orderedStages()
     */
    public function indexOf(CertificateRouteAccess $stage): int
    {
        $ordered = CertificateRouteAccess::orderedStages();
        foreach ($ordered as $i => $s) {
            if ($s === $stage) {
                return $i;
            }
        }
        return -1;
    }

    /**
     * @throws \Exception
     */
    public function verifyCertificates(): ?CertificateSetupProcess
    {
        $certPemLimitDate = $this->certificateService->certificateLimitDate('/signing-keys/cert.pem');
        $chainPemLimitDate = $this->certificateService->certificateLimitDate('/signing-keys/chain.pem');
        $fullchainPemLimitDate = $this->certificateService->certificateLimitDate('/signing-keys/fullchain.pem');

        if (
            $certPemLimitDate > 0 &&
            $chainPemLimitDate > 0 &&
            $fullchainPemLimitDate > 0
        ) {
            $certificateSetupProcess = new CertificateSetupProcess();
            $certificateSetupProcess->setStatus(ProcessStatusType::COMPLETED);
            $certificateSetupProcess->setRadsecproxyFormCompletedAt(new DateTimeImmutable());
            $certificateSetupProcess->setRadsecproxyConfigAppliedAt(new DateTimeImmutable());
            $certificateSetupProcess->setRadsecproxyTestResult(CertificateTestResult::PASSED);
            $certificateSetupProcess->setFreeradiusFormCompletedAt(new DateTimeImmutable());
            $certificateSetupProcess->setFreeradiusConfigAppliedAt(new DateTimeImmutable());
            $certificateSetupProcess->setFreeradiusTestResult(CertificateTestResult::PASSED);
            $certificateSetupProcess->setCreatedAt(new DateTimeImmutable());
            $certificateSetupProcess->setUpdatedAt(new DateTimeImmutable());

            $lastInstallation = $this->installationProgressRepository->getLast();
            if ($lastInstallation instanceof InstallationProgress) {
                $installationDTO = $this->installationService->fillDto($lastInstallation);
                $domain = $installationDTO->dbFreeradiusIp;
                $certificateSetupProcess->setFreeradiusDomainName($domain);
            }
            $this->entityManager->persist($certificateSetupProcess);
            $this->entityManager->flush();
            return $certificateSetupProcess;
        }
        return null;
    }

}
