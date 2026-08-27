<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827000100 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE audit_log (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, payload JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_AUDIT_USER FOREIGN KEY (user_id) REFERENCES app_users (id)');
    }
}
