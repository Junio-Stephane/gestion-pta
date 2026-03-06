<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251031064754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE personnel ADD fonction_per VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD chef_service_id VARCHAR(6) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2A37F5B5 FOREIGN KEY (chef_service_id) REFERENCES personnel (im_per)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E19D9AD2A37F5B5 ON service (chef_service_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE personnel DROP fonction_per');
        $this->addSql('ALTER TABLE service DROP FOREIGN KEY FK_E19D9AD2A37F5B5');
        $this->addSql('DROP INDEX UNIQ_E19D9AD2A37F5B5 ON service');
        $this->addSql('ALTER TABLE service DROP chef_service_id');
    }
}
