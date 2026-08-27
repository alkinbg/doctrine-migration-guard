<?php

declare(strict_types=1);

namespace TestMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class SchemaApiMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $schema->getTable('users');
    }
}
