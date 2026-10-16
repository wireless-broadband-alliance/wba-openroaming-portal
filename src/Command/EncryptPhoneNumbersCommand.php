<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\Type\EncryptedPhoneNumberType;
use App\Entity\User;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Throwable;

#[AsCommand(
    name: 'app:cra:encrypt-phone-numbers',
    description: 'Encrypts legacy plain-text phone numbers stored in User.phoneNumber.',
)]
class EncryptPhoneNumbersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
                'Automatically confirm the encryption process'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Validate everything but write nothing'
            );
    }

    /**
     * @throws MappingException
     * @throws Exception
     * @throws TypesException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool)$input->getOption('dry-run');

        if (!$dryRun && !$input->getOption('yes')) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will encrypt all legacy plain-text phone numbers.
                 Make sure you have a database backup. [y/N] ',
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');

                return Command::SUCCESS;
            }
        }

        // Fetch the connection first: the Doctrine middleware injects the vault key when it is created.
        $connection = $this->entityManager->getConnection();
        $metadata = $this->entityManager->getClassMetadata(User::class);
        $table = $connection->quoteSingleIdentifier($metadata->getTableName());
        $pk = $connection->quoteSingleIdentifier($metadata->getSingleIdentifierColumnName());
        $column = $connection->quoteSingleIdentifier($metadata->getColumnName('phoneNumber'));

        /** @var EncryptedPhoneNumberType $type */
        $type = Type::getType(EncryptedPhoneNumberType::NAME);
        $platform = $connection->getDatabasePlatform();
        $util = PhoneNumberUtil::getInstance();

        $encrypted = 0;
        $alreadyEncrypted = 0;

        $connection->beginTransaction();

        try {
            $rows = $connection->fetchAllAssociative(
                "SELECT $pk AS id, $column AS phone FROM $table WHERE $column IS NOT NULL AND $column != ''"
            );

            foreach ($rows as $row) {
                $raw = (string)$row['phone'];

                if (!EncryptedPhoneNumberType::isLegacyPlaintext($raw)) {
                    // Must already be valid ciphertext: this throws if it is neither (wrong key / garbage).
                    $type->convertToPHPValue($raw, $platform);
                    ++$alreadyEncrypted;
                    continue;
                }

                try {
                    $parsed = $util->parse($raw, PhoneNumberUtil::UNKNOWN_REGION);
                } catch (NumberParseException) {
                    throw new \RuntimeException(sprintf('User id=%s has an unparseable phone number.', $row['id']));
                }

                $encryptedValue = $type->convertToDatabaseValue($parsed, $platform);

                // Round-trip check before writing.
                $roundTrip = $type->convertToPHPValue($encryptedValue, $platform);
                if (
                    $roundTrip === null || $util->format($roundTrip, PhoneNumberFormat::E164) !== $util->format(
                        $parsed,
                        PhoneNumberFormat::E164
                    )
                ) {
                    throw new \RuntimeException(sprintf('Round-trip verification failed for user id=%s.', $row['id']));
                }

                if (!$dryRun) {
                    $connection->executeStatement(
                        "UPDATE $table SET $column = :val WHERE $pk = :id",
                        ['val' => $encryptedValue, 'id' => $row['id']]
                    );
                }

                ++$encrypted;
            }

            if ($dryRun) {
                $connection->rollBack();
            } else {
                $connection->commit();
            }
        } catch (Throwable $e) {
            $connection->rollBack();
            $output->writeln('<error>Aborted, nothing was changed:</error> ' . $e->getMessage());

            return Command::FAILURE;
        }

        $output->writeln(
            sprintf(
                '<info>%s:</info> %d phone number(s) encrypted, %d already encrypted.',
                $dryRun ? 'Dry run' : 'Success',
                $encrypted,
                $alreadyEncrypted
            )
        );

        return Command::SUCCESS;
    }
}
