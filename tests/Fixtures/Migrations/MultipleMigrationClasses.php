<?php

declare(strict_types=1);

namespace TestMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class FirstMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
    }
}

final class SecondMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
    }
}
