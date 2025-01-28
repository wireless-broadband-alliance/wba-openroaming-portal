<?php

namespace App\Command;

use App\Repository\EventRepository;
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
    name: 'clear:eventEntity',
    description: 'Clear events with null values from the Event entity',
)]
class ClearEventCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('clear:eventEntity')
            ->setDescription('Clear events with null values from the Event entity')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the action');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Confirmation prompt if --yes option is not provided
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            // phpcs:disable Generic.Files.LineLength.TooLong
            $question = new ConfirmationQuestion(
                'This action will delete all records from the Event entity where any field is null. Do you want to proceed? [y/N] ',
                false
            );
            // phpcs:enable
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $this->entityManager->beginTransaction();

        try {
            $events = $this->eventRepository->findEventsWithNullOrEmptyFields();
            $deletedCount = 0;

            foreach ($events as $event) {
                $this->entityManager->remove($event);
                $deletedCount++;
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $output->writeln(
                "<info>Success:</info> $deletedCount event(s) with null or empty values have been deleted."
            );
        } catch (Exception $e) {
            // Handle any exceptions and roll back in case of an error
            $this->entityManager->rollback();
            $output->writeln('<error>An error occurred:</error> ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
