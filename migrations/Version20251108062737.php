<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251108062737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tache (num_tache VARCHAR(20) NOT NULL, num_projet VARCHAR(20) DEFAULT NULL, titre_tache VARCHAR(40) NOT NULL, description_tache LONGTEXT DEFAULT NULL, commentaire_tache LONGTEXT DEFAULT NULL, date_debut_tache DATETIME NOT NULL, date_fin_tache DATETIME DEFAULT NULL, avancement_tache INT DEFAULT NULL, statut_tache VARCHAR(20) NOT NULL, INDEX IDX_93872075E934CB11 (num_projet), PRIMARY KEY(num_tache)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE tache ADD CONSTRAINT FK_93872075E934CB11 FOREIGN KEY (num_projet) REFERENCES projet (num_projet)');
        $this->addSql('ALTER TABLE projet ADD commentaire_pro LONGTEXT DEFAULT NULL, CHANGE avancement avancement_pro INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tache DROP FOREIGN KEY FK_93872075E934CB11');
        $this->addSql('DROP TABLE tache');
        $this->addSql('ALTER TABLE projet DROP commentaire_pro, CHANGE avancement_pro avancement INT DEFAULT NULL');
    }
}
