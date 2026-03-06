<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251027131806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE direction CHANGE statut_direction statut_direction VARCHAR(20) DEFAULT \'ACTIVE\' NOT NULL');
        $this->addSql('ALTER TABLE personnel CHANGE statut_per statut_per VARCHAR(30) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE direction CHANGE statut_direction statut_direction VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE personnel CHANGE statut_per statut_per VARCHAR(20) NOT NULL');
    }
}
