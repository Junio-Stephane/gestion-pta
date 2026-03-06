<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251015151517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE personnel ADD status VARCHAR(20) NOT NULL, ADD is_validated TINYINT(1) NOT NULL, CHANGE code_service code_service VARCHAR(20) DEFAULT NULL, CHANGE mdp_per mdp_per VARCHAR(255) NOT NULL, CHANGE role_per role_per VARCHAR(30) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE personnel DROP status, DROP is_validated, CHANGE code_service code_service VARCHAR(20) NOT NULL, CHANGE mdp_per mdp_per VARCHAR(60) NOT NULL, CHANGE role_per role_per VARCHAR(20) NOT NULL');
    }
}
