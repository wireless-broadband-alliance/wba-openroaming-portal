<?php

namespace App\Command;

use App\Entity\OTPcode;
use App\Exception\EncryptionException;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'app:cra:encrypt-otp-codes',
    description: 'Encrypt legacy plain-text OTP backup codes to comply with CRA.',
)]
class EncryptLegacyOTPCodesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EncryptionService $encryptionService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'yes',
            'y',
            InputOption::VALUE_NONE,
            'Automatically confirm the encryption process'
        );
    }

    /**
     * @throws EncryptionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will encrypt all legacy plain-text OTP backup codes. [y/N]',
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

            try {
                // If decryption succeeds, the code is already encrypted
                $this->encryptionService->decrypt($currentCode);
                $skipCount++;
            } catch (EncryptionException) {
                // If decryption throws an exception, encrypt the plain-text value
                $encryptedCode = $this->encryptionService->encrypt($currentCode);
                $otpCode->setCode($encryptedCode);
                $updatedCount++;
            }

            if ($updatedCount > 0 && $updatedCount % 100 === 0) {
                $this->entityManager->flush();
                $output->write('.');
            }
        }

        $this->entityManager->flush();

        $message = <<<EOL


<info>Success:</info> $updatedCount legacy OTP backup codes have been successfully encrypted.
<comment>Skipped:</comment> $skipCount codes were already encrypted.
<comment>Note:</comment> 2FA backup codes are now protected in compliance with CRA guidelines.
EOL;

        $output->write($message);
        $output->writeln(['']);

        return Command::SUCCESS;
    }
}
