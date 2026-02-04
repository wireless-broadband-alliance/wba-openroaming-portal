<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Throwable;

#[AsCommand(
    name: 'reset:convert-admin-roles',
    description: 'Convert Admin Roles and permissions to be a Super Admin, ' .
    'required for the new feature with the role hierarchy system and dashboard pages access',
)]
class ConvertAdminRolesForCertsReleaseCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('reset:convert-admin-roles')
            ->setDescription(
                'Convert Admin Roles and permissions to be a Super Admin,' .
                'required for the new feature with the role hierarchy system and dashboard pages access'
            )
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the reset');
    }

    /**
     * @throws RandomException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if (!$input->getOption('yes')) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    'This action will convert the admin role to a super admin. Do you wish to proceed? [y/N]',
                    false
                );

                if (!$helper->ask($input, $output, $question)) {
                    $output->writeln('<comment>Command aborted.</comment>');
                    return Command::SUCCESS;
                }
            }

            $this->resetAdminUser();

            $output->writeln(
                '<info>Success:</info> The admin roles have been configured for release 1.11.0.'
            );

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    /**
     * @throws RandomException
     */
    protected function resetAdminUser(): void
    {
        $admin = $this->userRepository->findFirstUser();

        if (!$admin instanceof User) {
            throw new RuntimeException('No users found in the database. Unable to promote admin.');
        }

        $admin->setRoles([AdminRoleType::ROLE_SUPER_ADMIN->value]);
        $admin->setPermissions([]);
        $admin->setIsVerified(true);
        $admin->setTwoFAcode((string)random_int(100000, 999999));
        $admin->setTwoFAcodeGeneratedAt(new DateTime());
        $admin->setTwoFAcodeIsActive(true);
        $admin->setCreatedAt(new DateTime());
        $this->entityManager->persist($admin);
        $this->entityManager->flush();
    }
}
