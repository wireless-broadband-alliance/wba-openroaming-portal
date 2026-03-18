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
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

#[AsCommand(
    name: 'prepare-release:v1100',
    description: 'Prepare for release v1.10.0: convert admin roles and update CA certificate path'
)]
class PrepareReleaseV1100Command extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Prepare for release v1.10.0: convert admin roles and update CA certificate path')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the reset and CA update');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if (!$input->getOption('yes')) {
                /** @var QuestionHelper $helper */
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    'This action will prepare v1.10.0 release: convert admin roles and update CA. Proceed? [y/N] ',
                    false
                );

                if (!$helper->ask($input, $output, $question)) {
                    $output->writeln('<comment>Command aborted.</comment>');
                    return Command::SUCCESS;
                }
            }

            // Convert admin roles
            $this->resetAdminUser();
            $output->writeln('<info>Admin roles updated for v1.10.0 release.</info>');

            // Check CA certificate
            $this->updateCaCertificate($output);

            $output->writeln('<info>v1.10.0 release preparation complete.</info>');

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    /**
     * Convert first admin user to super admin
     *
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

    /**
     * Copy ca.pem from certificate folder to signing-keys/ca/ca.pem
     */
    protected function updateCaCertificate(OutputInterface $output): void
    {
        $filesystem = new Filesystem();
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $source = $projectDir . '/signing-keys/ca.pem';
        $destinationDir = $projectDir . '/signing-keys/ca';
        $destination = $destinationDir . '/ca.pem';

        if (!file_exists($source)) {
            $output->writeln('<comment>No ca.pem found in certificates folder, skipping update.</comment>');
            return;
        }

        $filesystem->mkdir($destinationDir);
        $filesystem->copy($source, $destination, true);

        $output->writeln('<info>ca.pem located, updating location to signing-keys/ca/ca.pem</info>');
    }
}
