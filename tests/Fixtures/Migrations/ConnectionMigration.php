<?php

declare(strict_types=1);

namespace TestMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class ConnectionMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->connection->executeStatement('DROP TABLE connection_demo');
    }
}
