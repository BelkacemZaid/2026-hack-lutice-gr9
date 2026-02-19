<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219140958 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subscription (id BINARY(16) NOT NULL, client_name VARCHAR(30) NOT NULL, client_email VARCHAR(255) NOT NULL, offer_type VARCHAR(255) NOT NULL, domain VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, scaleway_server_id VARCHAR(255) DEFAULT NULL, public_ip VARCHAR(45) DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, logo_name VARCHAR(255) DEFAULT NULL, logo_size INT DEFAULT NULL, logo_mime_type VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_A3C664D38FBFBD64 (client_name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
