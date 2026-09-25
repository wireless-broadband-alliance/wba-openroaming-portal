<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Event;
use App\Entity\DeletedUserData;
use App\Entity\OTPcode;
use App\Entity\UserRadiusProfile;
use App\Entity\User;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use DateTimeImmutable;
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
    name: 'clear:expired-user-data',
    description: 'Purges soft-deleted users older than retention period 
    and clears expired OTP tokens to comply with CRA.',
)]
class CleanupExpiredDataCommand extends Command
{
    private const int USER_RETENTION_DAYS = 30;
    private const int OTP_EXPIRATION_HOURS = 1;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingRepository $settingRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the cleanup process');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userRetentionSetting = $this->settingRepository->findOneBy(
            ['name' => SettingName::USER_RETENTION_DAYS->value]
        );
        $userRetentionDays = $userRetentionSetting ?
            (int) $userRetentionSetting->getValue() : self::USER_RETENTION_DAYS;

        $otpExpirationSetting = $this->settingRepository->findOneBy(
            ['name' => SettingName::OTP_EXPIRATION_HOURS->value]
        );
        $otpExpirationHours = $otpExpirationSetting ?
            (int) $otpExpirationSetting->getValue() : self::OTP_EXPIRATION_HOURS;

        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf(
                    'This action will permanently purge soft-deleted users 
                    older than %d days and clear expired OTP tokens older than %d hour(s). [y/N] ',
                    $userRetentionDays,
                    $otpExpirationHours
                ),
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        try {
            $userRepo = $this->entityManager->getRepository(User::class);

            $userThresholdDate = new DateTimeImmutable()->modify(sprintf('-%d days', $userRetentionDays));

            $usersToPurge = $userRepo->createQueryBuilder('u')
                ->select('u.id')
                ->where('u.deletedAt <= :threshold')
                ->setParameter('threshold', $userThresholdDate)
                ->getQuery()
                ->getSingleColumnResult();

            $deletedUsersCount = 0;

            if ($usersToPurge !== []) {
                $this->entityManager->createQueryBuilder()
                    ->delete(Event::class, 'e')
                    ->where('e.user IN (:userIds)')
                    ->setParameter('userIds', $usersToPurge)
                    ->getQuery()
                    ->execute();

                $this->entityManager->createQueryBuilder()
                    ->delete(DeletedUserData::class, 'd')
                    ->where('d.user IN (:userIds)')
                    ->setParameter('userIds', $usersToPurge)
                    ->getQuery()
                    ->execute();

                $this->entityManager->createQueryBuilder()
                    ->delete(OTPcode::class, 'o')
                    ->where('o.user IN (:userIds)')
                    ->setParameter('userIds', $usersToPurge)
                    ->getQuery()
                    ->execute();

                $this->entityManager->createQueryBuilder()
                    ->delete(UserRadiusProfile::class, 'urp')
                    ->where('urp.user IN (:userIds)')
                    ->setParameter('userIds', $usersToPurge)
                    ->getQuery()
                    ->execute();

                $deletedUsersCount = $userRepo->createQueryBuilder('u')
                    ->delete()
                    ->where('u.id IN (:userIds)')
                    ->setParameter('userIds', $usersToPurge)
                    ->getQuery()
                    ->execute();
            }

            $otpThresholdDate = new DateTimeImmutable()->modify(sprintf('-%d hours', $otpExpirationHours));

            $clearedOtpCount = $userRepo->createQueryBuilder('u')
                ->update()
                ->set('u.twoFAcode', 'null')
                ->set('u.twoFAcodeGeneratedAt', 'null')
                ->set('u.twoFAcodeIsActive', '0')
                ->where('u.twoFAcodeGeneratedAt <= :threshold')
                ->setParameter('threshold', $otpThresholdDate)
                ->getQuery()
                ->execute();

            $message = <<<EOL

<info>Success:</info> Purged $deletedUsersCount soft-deleted users (older than $userRetentionDays days).
<info>Success:</info> Cleared expired OTP tokens for $clearedOtpCount users.
<comment>Note:</comment> Data retention and minimization enforced in compliance with CRA Annex I §1.5.
EOL;

            $output->write($message);
            $output->writeln(['']);
        } catch (Exception $e) {
            $output->writeln('An error occurred during data cleanup: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
