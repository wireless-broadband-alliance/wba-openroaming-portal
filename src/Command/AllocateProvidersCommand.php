<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\UserProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'reset:allocate-providers',
    description: 'Allocate providers info from User Entity to the UserExternalAuth Entity',
)]
class AllocateProvidersCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'reset:allocate-providers')
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                'Allocate providers info from User Entity to the UserExternalAuth Entity'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if the --yes option is provided (comes from a controller), then skip the confirmation prompt
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will allocate all providers from the Users Entity to the UserExternalAuth Entity. [y/N] ',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $io = new SymfonyStyle($input, $output);

        $userRepository = $this->entityManager->getRepository(User::class);

        // Fetch all users
        $users = $userRepository->findAll();

        foreach ($users as $user) {
            $this->allocateProviders($user);
        }

        $this->entityManager->flush();

        $io->success('Providers have been allocated successfully.');

        return Command::SUCCESS;
    }

    private function allocateProviders(User $user): void
    {
        // Check for SAML identifier
        if ($user->getSamlIdentifier() && !$this->userExternalAuthExists($user, UserProvider::SAML)) {
            $this->createUserExternalAuth($user, UserProvider::SAML, $user->getSamlIdentifier());
        }

        // Check for Google ID
        if ($user->getGoogleId() && !$this->userExternalAuthExists($user, UserProvider::GOOGLE_ACCOUNT)) {
            $this->createUserExternalAuth($user, UserProvider::GOOGLE_ACCOUNT, $user->getGoogleId());
        }

        // Check for Phone Number Portal Account
        if (
            $user->getPhoneNumber() && !$this->userExternalAuthExists(
                $user,
                UserProvider::PORTAL_ACCOUNT,
                UserProvider::PHONE_NUMBER
            )
        ) {
            $this->createUserExternalAuth($user, UserProvider::PORTAL_ACCOUNT, UserProvider::PHONE_NUMBER);
        }

        // Check for Email Portal Account
        if (
            $user->getEmail() && !$user->getPhoneNumber() && !$user->getGoogleId() && !$user->getSamlIdentifier(
            ) && !$this->userExternalAuthExists($user, UserProvider::PORTAL_ACCOUNT, UserProvider::EMAIL)
        ) {
            $this->createUserExternalAuth($user, UserProvider::PORTAL_ACCOUNT, UserProvider::EMAIL);
        }
    }

    private function userExternalAuthExists(User $user, string $provider, ?string $providerId = null): bool
    {
        $criteria = ['user' => $user, 'provider' => $provider];
        if ($providerId !== null) {
            $criteria['provider_id'] = $providerId;
        }

        $existingAuth = $this->entityManager->getRepository(UserExternalAuth::class)->findOneBy($criteria);
        return $existingAuth !== null;
    }

    private function createUserExternalAuth(User $user, string $provider, string $providerId): void
    {
        $userExternalAuth = new UserExternalAuth();
        $userExternalAuth->setUser($user);
        $userExternalAuth->setProvider($provider);
        $userExternalAuth->setProviderId($providerId);

        $this->entityManager->persist($userExternalAuth);
    }
}
