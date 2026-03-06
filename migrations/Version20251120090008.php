<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251120090008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE projet CHANGE im_per_createur im_per_createur VARCHAR(6) NOT NULL');
        $this->addSql('ALTER TABLE projet ADD CONSTRAINT FK_50159CA93326293C FOREIGN KEY (im_per_createur) REFERENCES personnel (im_per)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE projet DROP FOREIGN KEY FK_50159CA93326293C');
        $this->addSql('ALTER TABLE projet CHANGE im_per_createur im_per_createur VARCHAR(6) DEFAULT NULL');
    }
}
