<?php

namespace App\Command;

use App\Service\CertificateCheckerService;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'notify:superAdminWhenCertsExpires',
    description: 'Notify super Admin when certificates are about to expire',
)]

class NotifySuperAdminWhenCertsExpiresCommand extends Command
{
    public function __construct(
        private readonly CertificateCheckerService $certificateService,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    public function notifySuperAdminWhenCertsExpires(OutputInterface $output): void
    {
        $certificatePath = $this->parameterBag->get('kernel.project_dir') . '/signing-keys/cert.pem';
        $certificateLimitDate = strtotime(
            (string)$this->certificateService->getCertificateExpirationDate($certificatePath)
        );
        $realTime = time();
        $timeLeft = round(($certificateLimitDate - $realTime) / (86400)) - 1;
        $profileLimitDate = ((int)$timeLeft);

        if ($profileLimitDate < 30) {
            if ($profileLimitDate < 0) {
                #todo send "expired cert" email
            } else {
                #todo send  "cert is about to expire" email
            }
        }
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->notifySuperAdminWhenCertsExpires($output);
        $output->writeln('Super admin notified');

        return Command::SUCCESS;
    }
}