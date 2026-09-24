<?php

namespace App\Command;

use App\Entity\OTPcode;
use App\Exception\EncryptionException;
use App\Service\EncryptionService;
use App\Service\HashArgon2idService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'app:cra:hash-otp-codes',
    description: 'Migrate legacy plain-text or encrypted OTP backup codes to Argon2id hashes.',
)]
class HashLegacyOTPCodesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EncryptionService $encryptionService,
        private readonly HashArgon2idService $hashArgon2idService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'yes',
            'y',
            InputOption::VALUE_NONE,
            'Automatically confirm the hashing process'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            assert($helper instanceof QuestionHelper);

            $question = new ConfirmationQuestion(
                'This action will convert all legacy plain-text and encrypted OTP backup codes to Argon2id hashes. [y/N] ',
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

            if (!is_string($currentCode) || $currentCode === '') {
                $skipCount++;
                continue;
            }

            // Skip codes that are already hashed with Argon2id
            if (str_starts_with($currentCode, '$argon2id$')) {
                $skipCount++;
                continue;
            }

            // Retrieve the plain-text value (decrypting if it was previously encrypted)
            try {
                $plainCode = $this->encryptionService->decrypt($currentCode);
            } catch (EncryptionException) {
                // If decryption fails, the code was already in plain text
                $plainCode = $currentCode;
            }

            // Convert the plain-text code into an irreversible Argon2id hash
            $hashedCode = $this->hashArgon2idService->hash($plainCode);
            $otpCode->setCode($hashedCode);
            $updatedCount++;

            if ($updatedCount % 100 === 0) {
                $this->entityManager->flush();
                $output->write('.');
            }
        }

        $this->entityManager->flush();

        $message = <<<EOL


<info>Success:</info> $updatedCount legacy OTP backup codes have been hashed with Argon2id.
<comment>Skipped:</comment> $skipCount codes were already hashed or invalid.
<comment>Note:</comment> 2FA backup codes are now protected with irreversible hashes for CRA compliance.
EOL;

        $output->write($message);
        $output->writeln(['']);

        return Command::SUCCESS;
    }
}
