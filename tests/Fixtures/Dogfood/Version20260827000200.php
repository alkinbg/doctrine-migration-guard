<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827000200 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_users ADD COLUMN last_login_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE app_users ADD COLUMN last_seen_at DATETIME DEFAULT NULL');
    }
}
