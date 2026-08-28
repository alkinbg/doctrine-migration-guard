<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Fixtures\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class PreUpMigration extends AbstractMigration
{
    public function preUp(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE demo (id INT)');
    }
}
