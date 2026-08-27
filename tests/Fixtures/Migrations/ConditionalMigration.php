<?php

declare(strict_types=1);

namespace TestMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class ConditionalMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        if (PHP_VERSION_ID > 0) {
            $this->addSql('DROP TABLE conditional_demo');
        }
    }
}
