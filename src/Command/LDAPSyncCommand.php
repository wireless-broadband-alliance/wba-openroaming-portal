<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ldap:sync',
    description: 'Sync user account status with LDAP',
)]
class LDAPSyncCommand extends Command
{
    private $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;

        parent::__construct();
    }

    protected function configure(): void
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $ldapEnabledUsers = $this->userRepository->findLDAPEnabledUsers();
        $io->writeln('Found ' . count($ldapEnabledUsers) . ' LDAP enabled users');
        foreach ($ldapEnabledUsers as $user) {
            $io->writeln('Syncing ' . $user->saml_identifier . ' with LDAP');
            $ldapUser = $this->fetchUserFromLDAP($user->saml_identifier);

        }

        return Command::SUCCESS;
    }

    private function fetchUserFromLDAP(string $identifier)
    {

    }
}
