<?php

namespace App\Command;

use App\Entity\Setting;
use App\Enum\SettingName;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'reset:securityTxtSettings',
    description: 'Reset the security.txt settings (contact, expiry date and PGP fingerprint)',
)]
class ResetSecurityTxtCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                'Automatically confirm the reset'
            )
            ->addOption(
                'only-missing',
                null,
                InputOption::VALUE_NONE,
                'Only create the settings that do not exist yet, without changing existing values'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $onlyMissing = (bool)$input->getOption('only-missing');

        // --only-missing never overwrites anything, so it needs no confirmation
        if (!$onlyMissing && !$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will clear the security.txt contact, expiry date and PGP fingerprint. '
                . '/.well-known/security.txt will return 404 until they are configured again. [y/N] ',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $settings = [
            ['name' => SettingName::SECURITY_CONTACT->value, 'value' => ''],
            ['name' => SettingName::SECURITY_EXPIRES->value, 'value' => ''],
            ['name' => SettingName::SECURITY_PGP_FINGERPRINT->value, 'value' => ''],
        ];

        $created = 0;
        $reset = 0;
        $skipped = 0;

        $this->entityManager->beginTransaction();

        try {
            $settingsRepository = $this->entityManager->getRepository(Setting::class);

            foreach ($settings as $settingData) {
                $setting = $settingsRepository->findOneBy(['name' => $settingData['name']]);

                if ($setting === null) {
                    $setting = new Setting();
                    $setting->setName($settingData['name']);
                    $setting->setValue($settingData['value']);
                    $this->entityManager->persist($setting);
                    ++$created;
                    continue;
                }

                if ($onlyMissing) {
                    ++$skipped;
                    continue;
                }

                $setting->setValue($settingData['value']);
                ++$reset;
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $message = sprintf(
                <<<'EOL'

<info>Success:</info> The security.txt settings were processed (created: %d, reset: %d, skipped: %d).
<comment>Note:</comment> /.well-known/security.txt returns 404 until the contact and expiry date are configured.
      If you want to reset any other setting please check using this command:
      <fg=blue>php bin/console reset</>
EOL,
                $created,
                $reset,
                $skipped
            );

            $output->write($message);
            $output->writeln(['']);
        } catch (Exception $e) {
            $this->entityManager->rollback();
            $output->writeln('An error occurred while resetting settings: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
