<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827000300 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE app_users SET status = 'active' WHERE status IS NULL");
    }
}
