<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Fixtures\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class AddSqlExecutableArgumentMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE demo (id INT)',
            [$this->connection->executeStatement('DROP TABLE users')],
        );
    }
}
