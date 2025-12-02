<?php

namespace App\Command;

use App\Service\CertificateProcessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'clear:uploaded-certs',
    description: 'Clears unused uploaded certs from var/certs while keeping the ones in the current process.'
)]
class ClearUploadedCertsCommand extends Command
{
    public function __construct(
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $folder = $this->getProjectDir() . '/var/certs';
        $filesystem = new Filesystem();
        $finder = new Finder();

        if (!$filesystem->exists($folder)) {
            $output->writeln('<comment>Folder var/certs does not exist.</comment>');
            return Command::SUCCESS;
        }

        // 1. Fetch the current process via the service
        $currentProcess = $this->certificateProcessCheckerService->getCurrentProcess();

        // 2. Get the certificates for that process
        $inUseFiles = [];
        if ($currentProcess !== null) {
            $certificates = $currentProcess->getCertificates();
            foreach ($certificates as $certificate) {
                if ($certificate->getFilePath() !== null) {
                    $inUseFiles[] = $certificate->getFilePath();
                }
            }
        }

        $finder->files()->in($folder);
        $deletedCount = 0;

        // 3. Delete files that are not part of the current process
        foreach ($finder as $file) {
            if (!in_array($file->getFilename(), $inUseFiles, true)) {
                $filesystem->remove($file->getRealPath());
                $deletedCount++;
            }
        }

        $output->writeln("<info>Deleted $deletedCount unused file(s) from var/certs.</info>");
        return Command::SUCCESS;
    }

    private function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }
}
