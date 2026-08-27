<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827000800 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE news_posts ADD status VARCHAR(16) NOT NULL DEFAULT 'published', ADD author_id INT DEFAULT NULL");
    }
}
