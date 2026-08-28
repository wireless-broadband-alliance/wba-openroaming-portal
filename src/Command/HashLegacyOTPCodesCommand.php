<?php

namespace App\Command;

use App\Entity\OTPcode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'app:otp:hash-legacy-codes',
    description: 'Hashes legacy plain-text OTP backup codes using Argon2id to comply with CRA.',
)]
class HashLegacyOTPCodesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the hashing process');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will hash all legacy plain-text OTP backup codes using Argon2id. [y/N] ',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $output->writeln('Fetching codes...');

        $otpCodes = $this->entityManager->getRepository(OTPcode::class)->findAll();
        $updatedCount = 0;
        $skipCount = 0;

        foreach ($otpCodes as $otpCode) {
            $currentCode = $otpCode->getCode();

            if (strlen($currentCode) < 90) {
                $hashedCode = password_hash($currentCode, PASSWORD_ARGON2ID);
                $otpCode->setCode($hashedCode);
                $updatedCount++;
            } else {
                $skipCount++;
            }

            if ($updatedCount > 0 && $updatedCount % 100 === 0) {
                $this->entityManager->flush();
                $output->write('.');
            }
        }

        $this->entityManager->flush();

        $message = <<<EOL


<info>Success:</info> $updatedCount legacy OTP backup codes have been successfully hashed.
<comment>Skipped:</comment> $skipCount codes were already hashed.
<comment>Note:</comment> 2FA backup codes are now protected in compliance with CRA guidelines.
EOL;

        $output->write($message);
        $output->writeln(['']);

        return Command::SUCCESS;
    }
}
