<?php

namespace App\Command;

use App\Enum\UserProvider;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\VarDumper\VarDumper;

#[AsCommand(
    name: 'reset:update-phone-number-format',
    description: 'Update phone numbers for users to the new phoneNumber type with country code detection.',
)]
class ResetUpdatePhoneNumberFormatCommand extends Command
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        parent::__construct();
        $this->userRepository = $userRepository;
    }

    protected function configure(): void
    {
        $this
            ->setName('reset:update-phone-number-format')
            ->setDescription(
                'Update phone numbers for users to the new phoneNumber type with country code detection. This can or may implode the database if the detection of the country code is not well succeeded.'
            )
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the reset');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will update all phone numbers to the new format. This can or may implode the database if the detection of the country code is not well succeeded. Continue? [y/N] ',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        // Retrieve all users (since all should have a uuid)
        $users = $this->userRepository->findAll();

        // Filter users with provider_id equal to "PHONE_NUMBER"
        $filteredUsers = [];
        foreach ($users as $user) {
            foreach ($user->getUserExternalAuths() as $externalAuth) {
                if ($externalAuth->getProviderId() === UserProvider::PHONE_NUMBER) {
                    $filteredUsers[] = $user;
                    break; // No need to check other external auths for this user
                }
            }
        }

        // Dump filtered users to inspect
        VarDumper::dump($filteredUsers);

        // Stop execution
        return Command::SUCCESS;

        // $output->writeln('Phone number format update completed.');
        // return Command::SUCCESS;
    }
}