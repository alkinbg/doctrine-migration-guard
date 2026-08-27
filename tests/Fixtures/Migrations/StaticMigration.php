<?php

declare(strict_types=1);

namespace TestMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration as MigrationBase;

final class StaticMigration extends MigrationBase
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE demo (id INT)');
        $this->addSql('ALTER '.'TABLE demo ADD nickname VARCHAR(64) DEFAULT NULL');
        $this->addSql(<<<'SQL'
UPDATE demo SET touched = 1
WHERE id = 1
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE demo');
    }
}
