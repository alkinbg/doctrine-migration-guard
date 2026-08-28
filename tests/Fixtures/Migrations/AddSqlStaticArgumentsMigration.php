<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Fixtures\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class AddSqlStaticArgumentsMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE users SET active = ? WHERE id = ?',
            [0, 123],
            [Types::INTEGER, Types::INTEGER],
        );
    }
}
