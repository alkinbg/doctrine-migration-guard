<?php

declare(strict_types=1);

namespace TestMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class DynamicMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $sql = 'DROP TABLE users';
        $this->addSql($sql);
    }
}
