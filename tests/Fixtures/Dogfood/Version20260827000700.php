<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827000700 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_users ADD last_login_at DATETIME DEFAULT NULL, ADD last_seen_at DATETIME DEFAULT NULL, ADD password_changed_at DATETIME DEFAULT NULL');
    }
}
