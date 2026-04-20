<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420175827 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

   public function up(Schema $schema): void
    {
        $this->addSql('UPDATE user_request SET is_active = 1 WHERE is_active IS NULL');
        $this->addSql('ALTER TABLE user_request MODIFY is_active TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_request MODIFY is_active TINYINT(1) NULL');
    }
}
