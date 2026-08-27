<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Integration\Cli;

use AlkinBG\DoctrineMigrationGuard\Cli\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

final class DogfoodTest extends TestCase
{
    public function testRealisticMigrationSetFailsClosedWithoutSkippingLaterFiles(): void
    {
        $directory = dirname(__DIR__, 2).'/Fixtures/Dogfood';
        $files = [
            'Version20260827000100.php',
            'Version20260827000200.php',
            'Version20260827000300.php',
            'Version20260827000400.php',
            'Version20260827000500.php',
            'Version20260827000600.php',
            'Version20260827000700.php',
            'Version20260827000800.php',
            'Version20260827000900.php',
        ];

        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        $tester->run(['paths' => [$directory]], [
            'interactive' => false,
            'capture_stderr_separately' => false,
        ]);

        $display = $tester->getDisplay();

        self::assertSame(2, $tester->getStatusCode());
        self::assertStringContainsString('Result: INCOMPLETE', $display);

        foreach ($files as $file) {
            self::assertStringContainsString($directory.'/'.$file, $display);
        }

        self::assertStringContainsString('CREATE UNIQUE INDEX uniq_users_email ON app_users (email)', $display);
        self::assertStringContainsString('Creating a unique index can fail when existing rows contain duplicate values.', $display);
        self::assertStringContainsString('ALTER TABLE audit_log DROP COLUMN legacy_payload', $display);
        self::assertStringContainsString('Dropping a column permanently removes stored data.', $display);

        self::assertStringContainsString('ALTER TABLE app_users ADD last_login_at DATETIME DEFAULT NULL, ADD last_seen_at DATETIME DEFAULT NULL, ADD password_changed_at DATETIME DEFAULT NULL', $display);
        self::assertStringContainsString("ALTER TABLE news_posts ADD status VARCHAR(16) NOT NULL DEFAULT 'published', ADD author_id INT DEFAULT NULL", $display);
        self::assertStringContainsString('Multiple ALTER TABLE actions in one statement are not supported.', $display);
        self::assertStringContainsString('UNANALYZED', $display);
    }
}
