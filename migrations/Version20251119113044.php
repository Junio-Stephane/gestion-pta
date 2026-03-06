<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251119113044 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA9E85B49B');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA9E85B49B FOREIGN KEY (im_per) REFERENCES personnel (im_per)');
        $this->addSql('ALTER TABLE tache CHANGE num_tache num_tache INT AUTO_INCREMENT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tache CHANGE num_tache num_tache VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA9E85B49B');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA9E85B49B FOREIGN KEY (im_per) REFERENCES personnel (im_per) ON DELETE CASCADE');
    }
}
