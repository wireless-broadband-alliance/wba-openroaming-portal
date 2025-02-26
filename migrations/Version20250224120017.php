<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Enum\UserTwoFactorAuthenticationStatus;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250224120017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE OTPcode DROP FOREIGN KEY FK_D672B040E8154F67');
        $this->addSql('ALTER TABLE TwoFactorAuthentication DROP FOREIGN KEY FK_3BD9AAE2A76ED395');
        $this->addSql('DROP TABLE TwoFactorAuthentication');
        $this->addSql('DROP INDEX IDX_D672B040E8154F67 ON OTPcode');
        $this->addSql('ALTER TABLE OTPcode CHANGE twoFactorAuthentication_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE OTPcode ADD CONSTRAINT FK_D672B040A76ED395 FOREIGN KEY (user_id) REFERENCES User (id)');
        $this->addSql('CREATE INDEX IDX_D672B040A76ED395 ON OTPcode (user_id)');
        $this->addSql('ALTER TABLE User ADD twoFAsecret VARCHAR(255) DEFAULT NULL, ADD twoFAtype VARCHAR(255) DEFAULT 0, ADD twoFAcode VARCHAR(10) DEFAULT NULL, ADD twoFAcodeGeneratedAt DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE TwoFactorAuthentication (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, secret VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, type VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, code VARCHAR(10) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, codeGeneratedAt DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_3BD9AAE2A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE TwoFactorAuthentication ADD CONSTRAINT FK_3BD9AAE2A76ED395 FOREIGN KEY (user_id) REFERENCES User (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE User DROP twoFAsecret, DROP twoFAtype, DROP twoFAcode, DROP twoFAcodeGeneratedAt');
        $this->addSql('ALTER TABLE OTPcode DROP FOREIGN KEY FK_D672B040A76ED395');
        $this->addSql('DROP INDEX IDX_D672B040A76ED395 ON OTPcode');
        $this->addSql('ALTER TABLE OTPcode CHANGE user_id twoFactorAuthentication_id INT NOT NULL');
        $this->addSql('ALTER TABLE OTPcode ADD CONSTRAINT FK_D672B040E8154F67 FOREIGN KEY (twoFactorAuthentication_id) REFERENCES TwoFactorAuthentication (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_D672B040E8154F67 ON OTPcode (twoFactorAuthentication_id)');
    }
}
