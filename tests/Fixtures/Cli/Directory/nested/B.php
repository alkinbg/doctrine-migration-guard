<?php

declare(strict_types=1);

namespace CliFixtures\Directory\Nested;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class B extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cli_b (id INT)');
    }
}
