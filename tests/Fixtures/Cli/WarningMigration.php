<?php

declare(strict_types=1);

namespace CliFixtures;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class WarningMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE users SET active=0 WHERE id=1');
    }
}
