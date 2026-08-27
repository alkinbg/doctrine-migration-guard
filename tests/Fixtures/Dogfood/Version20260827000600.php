<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827000600 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $schema->getTable('app_users');
    }
}
