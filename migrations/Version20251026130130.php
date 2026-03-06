<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251026130130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE direction ADD statut_direction VARCHAR(20) DEFAULT \'ACTIVE\' NOT NULL');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA9E85B49B');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA9E85B49B FOREIGN KEY (im_per) REFERENCES personnel (im_per)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE direction DROP statut_direction');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA9E85B49B');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA9E85B49B FOREIGN KEY (im_per) REFERENCES personnel (im_per) ON DELETE CASCADE');
    }
}
