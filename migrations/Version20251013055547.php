<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013055547 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE direction (code_direction VARCHAR(20) NOT NULL, nom_direction VARCHAR(50) NOT NULL, PRIMARY KEY(code_direction)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE personnel (im_per VARCHAR(6) NOT NULL, code_direction VARCHAR(20) DEFAULT NULL, code_service VARCHAR(20) NOT NULL, nom_per VARCHAR(50) NOT NULL, prenom_per VARCHAR(40) DEFAULT NULL, email_per VARCHAR(55) NOT NULL, tel_per VARCHAR(20) DEFAULT NULL, mdp_per VARCHAR(60) NOT NULL, role_per VARCHAR(20) NOT NULL, date_creation_per DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_A6BCF3DE84F51EF6 (email_per), UNIQUE INDEX UNIQ_A6BCF3DE920FA98B (code_direction), INDEX IDX_A6BCF3DED93ADE4E (code_service), PRIMARY KEY(im_per)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE personnel_projet (im_per VARCHAR(6) NOT NULL, num_projet VARCHAR(20) NOT NULL, INDEX IDX_7CEBCB499E85B49B (im_per), INDEX IDX_7CEBCB49E934CB11 (num_projet), PRIMARY KEY(im_per, num_projet)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE projet (num_projet VARCHAR(20) NOT NULL, titre_pro VARCHAR(40) NOT NULL, description_pro LONGTEXT DEFAULT NULL, budget_pro NUMERIC(10, 2) NOT NULL, date_debut_pro DATETIME NOT NULL, date_fin_pro DATETIME DEFAULT NULL, avancement INT DEFAULT NULL, statut_pro VARCHAR(20) NOT NULL, PRIMARY KEY(num_projet)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service (code_service VARCHAR(20) NOT NULL, code_direction VARCHAR(20) DEFAULT NULL, nom_service VARCHAR(50) NOT NULL, INDEX IDX_E19D9AD2920FA98B (code_direction), PRIMARY KEY(code_service)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE personnel ADD CONSTRAINT FK_A6BCF3DE920FA98B FOREIGN KEY (code_direction) REFERENCES direction (code_direction)');
        $this->addSql('ALTER TABLE personnel ADD CONSTRAINT FK_A6BCF3DED93ADE4E FOREIGN KEY (code_service) REFERENCES service (code_service)');
        $this->addSql('ALTER TABLE personnel_projet ADD CONSTRAINT FK_7CEBCB499E85B49B FOREIGN KEY (im_per) REFERENCES personnel (im_per)');
        $this->addSql('ALTER TABLE personnel_projet ADD CONSTRAINT FK_7CEBCB49E934CB11 FOREIGN KEY (num_projet) REFERENCES projet (num_projet)');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2920FA98B FOREIGN KEY (code_direction) REFERENCES direction (code_direction)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE personnel DROP FOREIGN KEY FK_A6BCF3DE920FA98B');
        $this->addSql('ALTER TABLE personnel DROP FOREIGN KEY FK_A6BCF3DED93ADE4E');
        $this->addSql('ALTER TABLE personnel_projet DROP FOREIGN KEY FK_7CEBCB499E85B49B');
        $this->addSql('ALTER TABLE personnel_projet DROP FOREIGN KEY FK_7CEBCB49E934CB11');
        $this->addSql('ALTER TABLE service DROP FOREIGN KEY FK_E19D9AD2920FA98B');
        $this->addSql('DROP TABLE direction');
        $this->addSql('DROP TABLE personnel');
        $this->addSql('DROP TABLE personnel_projet');
        $this->addSql('DROP TABLE projet');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
