<?php

namespace App\Command;

use App\Entity\UserExternalAuth;
use App\Enum\UserProvider;
use App\Service\ExternalIdentifierHasher;
use Doctrine\DBAL\ArrayParameterType;
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
    description: 'Hashes legacy plain-text OAuth provider IDs for Google and Microsoft accounts to comply with CRA.',
)]
class HashLegacyProviderIdsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExternalIdentifierHasher $providerIdHasher,
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
                'Automatically confirm the hashing process'
            );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will hash all legacy plain-text Google/Microsoft provider IDs. [y/N] ',
                false
            );
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
            $sql = sprintf(
                'SELECT id, provider_id FROM %s WHERE provider_id IS NOT NULL AND provider IN (:providers)',
                $tableName
            );
            $auths = $connection->fetchAllAssociative(
                $sql,
                ['providers' => [UserProvider::GOOGLE_ACCOUNT->value, UserProvider::MICROSOFT_ACCOUNT->value]],
                ['providers' => ArrayParameterType::STRING]
            );

            $updatedCount = 0;

            foreach ($auths as $row) {
                $originalValue = $row['provider_id'];

                if (preg_match('/^[a-f0-9]{64}$/i', $originalValue)) {
                    continue;
                }

                $hashedValue = $this->providerIdHasher->hash($originalValue);

                $updateSql = sprintf('UPDATE %s SET provider_id = :provider_id WHERE id = :id', $tableName);
                $connection->executeStatement(
                    $updateSql,
                    ['provider_id' => $hashedValue, 'id' => $row['id']]
                );

                $updatedCount++;
            }

            $connection->commit();

            $message = <<<EOL

<info>Success:</info> $updatedCount legacy Google/Microsoft provider IDs have been successfully hashed.
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
