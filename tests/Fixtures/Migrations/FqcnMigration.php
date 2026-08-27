<?php

declare(strict_types=1);

namespace TestMigrations;

use Doctrine\DBAL\Schema\Schema;

final class FqcnMigration extends \Doctrine\Migrations\AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE fqcn_demo (id INT)');
    }
}
