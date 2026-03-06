<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251126091306 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, im_per VARCHAR(6) DEFAULT NULL, message VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, is_read TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BF5476CA9E85B49B (im_per), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tache (num_tache INT AUTO_INCREMENT NOT NULL, num_projet VARCHAR(20) DEFAULT NULL, titre_tache VARCHAR(40) NOT NULL, description_tache LONGTEXT DEFAULT NULL, commentaire_tache LONGTEXT DEFAULT NULL, date_debut_tache DATETIME NOT NULL, date_fin_tache DATETIME DEFAULT NULL, avancement_tache INT DEFAULT NULL, statut_tache VARCHAR(20) NOT NULL, INDEX IDX_93872075E934CB11 (num_projet), PRIMARY KEY(num_tache)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA9E85B49B FOREIGN KEY (im_per) REFERENCES personnel (im_per)');
        $this->addSql('ALTER TABLE tache ADD CONSTRAINT FK_93872075E934CB11 FOREIGN KEY (num_projet) REFERENCES projet (num_projet)');
        $this->addSql('ALTER TABLE direction ADD statut_direction VARCHAR(20) DEFAULT \'ACTIVE\' NOT NULL');
        $this->addSql('ALTER TABLE personnel ADD reset_token VARCHAR(100) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD statut_per VARCHAR(30) NOT NULL, ADD fonction_per VARCHAR(30) DEFAULT NULL, DROP status');
        $this->addSql('ALTER TABLE projet ADD code_service VARCHAR(20) DEFAULT NULL, ADD commentaire_pro LONGTEXT DEFAULT NULL, CHANGE budget_pro budget_pro NUMERIC(15, 2) NOT NULL, CHANGE im_per_createur im_per_createur VARCHAR(6) NOT NULL, CHANGE avancement avancement_pro INT DEFAULT NULL');
        $this->addSql('ALTER TABLE projet ADD CONSTRAINT FK_50159CA93326293C FOREIGN KEY (im_per_createur) REFERENCES personnel (im_per)');
        $this->addSql('ALTER TABLE projet ADD CONSTRAINT FK_50159CA9D93ADE4E FOREIGN KEY (code_service) REFERENCES service (code_service)');
        $this->addSql('CREATE INDEX IDX_50159CA9D93ADE4E ON projet (code_service)');
        $this->addSql('ALTER TABLE service ADD chef_service_id VARCHAR(6) DEFAULT NULL, ADD statut_service VARCHAR(20) DEFAULT \'EN_ATTENTE\' NOT NULL');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2A37F5B5 FOREIGN KEY (chef_service_id) REFERENCES personnel (im_per)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E19D9AD2A37F5B5 ON service (chef_service_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA9E85B49B');
        $this->addSql('ALTER TABLE tache DROP FOREIGN KEY FK_93872075E934CB11');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE tache');
        $this->addSql('ALTER TABLE direction DROP statut_direction');
        $this->addSql('ALTER TABLE projet DROP FOREIGN KEY FK_50159CA93326293C');
        $this->addSql('ALTER TABLE projet DROP FOREIGN KEY FK_50159CA9D93ADE4E');
        $this->addSql('DROP INDEX IDX_50159CA9D93ADE4E ON projet');
        $this->addSql('ALTER TABLE projet DROP code_service, DROP commentaire_pro, CHANGE im_per_createur im_per_createur VARCHAR(6) DEFAULT NULL, CHANGE budget_pro budget_pro NUMERIC(10, 2) NOT NULL, CHANGE avancement_pro avancement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE personnel ADD status VARCHAR(20) NOT NULL, DROP reset_token, DROP reset_token_expires_at, DROP statut_per, DROP fonction_per');
        $this->addSql('ALTER TABLE service DROP FOREIGN KEY FK_E19D9AD2A37F5B5');
        $this->addSql('DROP INDEX UNIQ_E19D9AD2A37F5B5 ON service');
        $this->addSql('ALTER TABLE service DROP chef_service_id, DROP statut_service');
    }
}
