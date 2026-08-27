<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Integration\Cli;

use AlkinBG\DoctrineMigrationGuard\Cli\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

final class AnalyzeCommandTest extends TestCase
{
    public function testInfoAndWarningMigrationPasses(): void
    {
        $tester = $this->runApplication([$this->fixture('StaticMigration.php')]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Result: PASSED', $tester->getDisplay());
    }

    public function testHighMigrationFails(): void
    {
        $tester = $this->runApplication([$this->cliFixture('HighMigration.php')]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Result: FAILED', $tester->getDisplay());
        self::assertStringContainsString('HIGH', $tester->getDisplay());
    }

    public function testDynamicMigrationIsIncomplete(): void
    {
        $tester = $this->runApplication([$this->fixture('DynamicMigration.php')]);

        self::assertSame(2, $tester->getStatusCode());
        self::assertStringContainsString('Result: INCOMPLETE', $tester->getDisplay());
    }

    public function testIncompleteWinsOverFailed(): void
    {
        $tester = $this->runApplication([$this->cliFixture('HighMigration.php'), $this->fixture('DynamicMigration.php')]);

        self::assertSame(2, $tester->getStatusCode());
        self::assertStringContainsString('HIGH', $tester->getDisplay());
        self::assertStringContainsString('UNANALYZED', $tester->getDisplay());
    }

    public function testMissingPathDoesNotHideValidFileFindings(): void
    {
        $missing = $this->cliFixture('missing.php');
        $tester = $this->runApplication([$missing, $this->cliFixture('HighMigration.php')]);

        self::assertSame(2, $tester->getStatusCode());
        self::assertStringContainsString($missing, $tester->getDisplay());
        self::assertStringContainsString('UPDATE users SET active=0', $tester->getDisplay());
    }

    public function testDirectoryInputIsRecursiveAndDeterministic(): void
    {
        $tester = $this->runApplication([$this->cliFixture('Directory')]);
        $display = $tester->getDisplay();

        self::assertSame(0, $tester->getStatusCode());
        $a = strpos($display, 'A.php');
        $b = strpos($display, 'nested/B.php');
        self::assertNotFalse($a);
        self::assertNotFalse($b);
        self::assertLessThan($b, $a);
    }

    public function testJsonOutputContainsOnlyValidJsonAndPreservesExitCode(): void
    {
        $tester = $this->runApplication([$this->cliFixture('HighMigration.php')], 'json');
        $display = $tester->getDisplay();
        $payload = json_decode($display, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $tester->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame(1, $payload['schema_version']);
        self::assertSame('failed', $payload['result']);
        self::assertStringNotContainsString('Migration Guard', $display);
    }

    public function testInvalidFormatReturnsUsageErrorWithoutStackTrace(): void
    {
        $tester = $this->runApplication([$this->cliFixture('HighMigration.php')], 'xml');

        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Unsupported format. Expected text or json.', $tester->getDisplay());
        self::assertStringNotContainsString('#0 ', $tester->getDisplay());
    }

    public function testApplicationUsesAnalyzeAsDefaultCommand(): void
    {
        $tester = $this->runApplication([$this->cliFixture('WarningMigration.php')]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('WARNING', $tester->getDisplay());
    }

    /** @param list<string> $paths */
    private function runApplication(array $paths, string $format = 'text'): ApplicationTester
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        $tester->run([
            'paths' => $paths,
            '--format' => $format,
        ], [
            'interactive' => false,
            'capture_stderr_separately' => false,
        ]);

        return $tester;
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__, 2).'/Fixtures/Migrations/'.$name;
    }

    private function cliFixture(string $name): string
    {
        return dirname(__DIR__, 2).'/Fixtures/Cli/'.$name;
    }
}
