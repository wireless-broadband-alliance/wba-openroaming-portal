<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Setting;
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
    name: 'reset:timeStampFreeradiusCron',
    description: 'Reset Timestamp for Freeradius cron'
)]
class ResetTimestampFreeradiusCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('reset:timeStampFreeradiusCron')
            ->setDescription('Reset Timestamp for Freeradius cron')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the reset');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if the --yes option is provided (comes from a controller), then skip the confirmation prompt
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('This action will RESET the TIMESTAMP used by the FREERADIUS cron job on the portal. ' .
                'This is a sensitive operation and should only be performed in **very specific scenarios**. ' .
                'Are you sure you want to proceed? [y/N]', false);
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $settings = [
            ['name' => 'TIME_STAMP_FREERADIUS_CRON', 'value' => (string)time()],
        ];

        // Begin a database transaction to ensure data consistency
        $this->entityManager->beginTransaction();

        try {
            $settingsRepository = $this->entityManager->getRepository(Setting::class);

            foreach ($settings as $settingData) {
                $name = $settingData['name'];
                $value = $settingData['value'];

                // Look for all the settings using the name
                $setting = $settingsRepository->findOneBy(['name' => $name]);

                if ($setting !== null) {
                    // Update the already existing value
                    $setting->setValue($value);
                } else {
                    // If it doesn't exist, create a new setting from the $setting
                    $setting = new Setting();
                    $setting->setName($name);
                    $setting->setValue($value);
                    $this->entityManager->persist($setting);
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $message = <<<EOL

<info>Success:</info> Timestamp for Freeradius cron have been set to the current time.
<comment>Note:</comment> If you want to reset any another setting please check using this command:
      <fg=blue>php bin/console reset</>
EOL;

            // Output the styled message
            $output->write($message);
            $output->writeln(['']);
        } catch (Exception $e) {
            // Handle any exceptions and roll back in case of an error
            $this->entityManager->rollback();
            $output->writeln('An error occurred while resetting settings: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
