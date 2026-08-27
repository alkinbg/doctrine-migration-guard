<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827000500 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $table = 'app_users';
        $this->addSql('ALTER TABLE '.$table.' ADD COLUMN nickname VARCHAR(64) DEFAULT NULL');
    }
}
