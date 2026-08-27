<?php

declare(strict_types=1);

namespace CliFixtures\Directory;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class A extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cli_a (id INT)');
    }
}
