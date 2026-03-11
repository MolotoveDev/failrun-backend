<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311180628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE clips (id INT AUTO_INCREMENT NOT NULL, user_id_id INT NOT NULL, game_id_id INT DEFAULT NULL, clip_title VARCHAR(255) NOT NULL, clip_link VARCHAR(2555) NOT NULL, clip_description VARCHAR(255) DEFAULT NULL, clip_date DATE NOT NULL, clip_status INT NOT NULL, INDEX IDX_C8751BE49D86650F (user_id_id), INDEX IDX_C8751BE44D77E7D8 (game_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE games (id INT AUTO_INCREMENT NOT NULL, game_name VARCHAR(255) NOT NULL, game_description VARCHAR(2555) DEFAULT NULL, cover_img VARCHAR(2555) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE mark (id INT AUTO_INCREMENT NOT NULL, clip_id_id INT NOT NULL, id_mark_type_id INT NOT NULL, mark_rate DOUBLE PRECISION NOT NULL, mark_date DATE NOT NULL, INDEX IDX_6674F27147F1D66 (clip_id_id), INDEX IDX_6674F271871F6F58 (id_mark_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE mark_type (id INT AUTO_INCREMENT NOT NULL, mark_name VARCHAR(255) NOT NULL, mark_description VARCHAR(255) DEFAULT NULL, mark_logo_url VARCHAR(2555) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL COMMENT \'(DC2Type:json)\', password VARCHAR(255) NOT NULL, username VARCHAR(255) NOT NULL, register_date DATE NOT NULL, role INT NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_rate (id INT AUTO_INCREMENT NOT NULL, user_id_id INT NOT NULL, clip_id_id INT NOT NULL, rate INT NOT NULL, user_comment VARCHAR(2555) DEFAULT NULL, rate_date DATE NOT NULL, INDEX IDX_A56D73F09D86650F (user_id_id), INDEX IDX_A56D73F047F1D66 (clip_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_request (id INT AUTO_INCREMENT NOT NULL, user_id_id INT NOT NULL, title_request VARCHAR(255) NOT NULL, description_request VARCHAR(2555) NOT NULL, date_request DATE NOT NULL, status_request INT NOT NULL, INDEX IDX_639A91959D86650F (user_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clips ADD CONSTRAINT FK_C8751BE49D86650F FOREIGN KEY (user_id_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE clips ADD CONSTRAINT FK_C8751BE44D77E7D8 FOREIGN KEY (game_id_id) REFERENCES games (id)');
        $this->addSql('ALTER TABLE mark ADD CONSTRAINT FK_6674F27147F1D66 FOREIGN KEY (clip_id_id) REFERENCES clips (id)');
        $this->addSql('ALTER TABLE mark ADD CONSTRAINT FK_6674F271871F6F58 FOREIGN KEY (id_mark_type_id) REFERENCES mark_type (id)');
        $this->addSql('ALTER TABLE user_rate ADD CONSTRAINT FK_A56D73F09D86650F FOREIGN KEY (user_id_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_rate ADD CONSTRAINT FK_A56D73F047F1D66 FOREIGN KEY (clip_id_id) REFERENCES clips (id)');
        $this->addSql('ALTER TABLE user_request ADD CONSTRAINT FK_639A91959D86650F FOREIGN KEY (user_id_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE clips DROP FOREIGN KEY FK_C8751BE49D86650F');
        $this->addSql('ALTER TABLE clips DROP FOREIGN KEY FK_C8751BE44D77E7D8');
        $this->addSql('ALTER TABLE mark DROP FOREIGN KEY FK_6674F27147F1D66');
        $this->addSql('ALTER TABLE mark DROP FOREIGN KEY FK_6674F271871F6F58');
        $this->addSql('ALTER TABLE user_rate DROP FOREIGN KEY FK_A56D73F09D86650F');
        $this->addSql('ALTER TABLE user_rate DROP FOREIGN KEY FK_A56D73F047F1D66');
        $this->addSql('ALTER TABLE user_request DROP FOREIGN KEY FK_639A91959D86650F');
        $this->addSql('DROP TABLE clips');
        $this->addSql('DROP TABLE games');
        $this->addSql('DROP TABLE mark');
        $this->addSql('DROP TABLE mark_type');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_rate');
        $this->addSql('DROP TABLE user_request');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
