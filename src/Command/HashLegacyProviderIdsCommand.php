<?php

namespace App\Command;

use App\Doctrine\Type\HmacStringType;
use App\Entity\UserExternalAuth;
use Doctrine\DBAL\Types\Type;
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
    name: 'app:auth:hash-legacy-ids',
    description: 'Hashes legacy plain-text OAuth provider IDs to comply with CRA.',
)]
class HashLegacyProviderIdsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the hashing process');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('This action will hash all legacy plain-text provider IDs. [y/N] ', false);
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $connection = $this->entityManager->getConnection();
        $tableName = $this->entityManager->getClassMetadata(UserExternalAuth::class)->getTableName();

        $connection->beginTransaction();

        try {
            $sql = sprintf('SELECT id, provider_id FROM %s WHERE provider_id IS NOT NULL', $tableName);
            $auths = $connection->fetchAllAssociative($sql);

            /** @var HmacStringType $type */
            $type = Type::getType(HmacStringType::NAME);
            $platform = $connection->getDatabasePlatform();

            $updatedCount = 0;

            foreach ($auths as $row) {
                $originalValue = $row['provider_id'];

                // Ignora se já for uma hash (64 caracteres hexadecimais)
                if (preg_match('/^[a-f0-9]{64}$/i', $originalValue)) {
                    continue;
                }

                $hashedValue = $type->convertToDatabaseValue($originalValue, $platform);

                $updateSql = sprintf('UPDATE %s SET provider_id = :provider_id WHERE id = :id', $tableName);
                $connection->executeStatement(
                    $updateSql,
                    ['provider_id' => $hashedValue, 'id' => $row['id']]
                );

                $updatedCount++;
            }

            $connection->commit();

            $message = <<<EOL

<info>Success:</info> $updatedCount legacy provider IDs have been successfully hashed.
<comment>Note:</comment> All external authentications are now compliant with CRA Annex I §1.3 and §1.5.
EOL;

            $output->write($message);
            $output->writeln(['']);
        } catch (Exception $e) {
            $connection->rollBack();
            $output->writeln('An error occurred while hashing IDs: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}