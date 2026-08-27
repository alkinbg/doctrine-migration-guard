<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Unit\Reporting;

use AlkinBG\DoctrineMigrationGuard\Analysis\AnalysisResult;
use AlkinBG\DoctrineMigrationGuard\Analysis\FileAnalysisResult;
use AlkinBG\DoctrineMigrationGuard\Analysis\Finding;
use AlkinBG\DoctrineMigrationGuard\Analysis\Severity;
use AlkinBG\DoctrineMigrationGuard\Reporting\TextReporter;
use PHPUnit\Framework\TestCase;

final class TextReporterTest extends TestCase
{
    public function testRendersDeterministicHumanReadableResult(): void
    {
        $result = new AnalysisResult([
            new FileAnalysisResult('migrations/A.php', [
                new Finding(Severity::Info, 10, "CREATE   TABLE\n demo (id INT)", 'Created table.'),
                new Finding(Severity::Warning, 20, 'CREATE INDEX idx ON demo(id)', 'Index review.'),
                new Finding(Severity::High, 30, 'UPDATE demo SET x=1', 'Unbounded update.'),
            ]),
            new FileAnalysisResult('migrations/B.php', [
                new Finding(Severity::Unanalyzed, null, null, 'Unsupported migration code.'),
            ]),
        ]);

        $output = (new TextReporter())->render($result);

        self::assertStringStartsWith("Migration Guard\n\n", $output);
        self::assertStringContainsString("migrations/A.php\n", $output);
        self::assertStringContainsString('INFO', $output);
        self::assertStringContainsString('line 10', $output);
        self::assertStringContainsString('CREATE TABLE demo (id INT)', $output);
        self::assertStringContainsString("migrations/B.php\n", $output);
        self::assertStringContainsString('line -', $output);
        self::assertStringContainsString('SQL unavailable', $output);
        self::assertStringContainsString("Summary\n", $output);
        self::assertStringContainsString('INFO:       1', $output);
        self::assertStringContainsString('WARNING:    1', $output);
        self::assertStringContainsString('HIGH:       1', $output);
        self::assertStringContainsString('CRITICAL:   0', $output);
        self::assertStringContainsString('UNANALYZED: 1', $output);
        self::assertStringEndsWith("Result: INCOMPLETE\n", $output);
        self::assertStringNotContainsString('SAFE', $output);
    }

    public function testCompactsLongSqlToFixedMaximum(): void
    {
        $sql = 'UPDATE demo SET note = '.str_repeat("'x'", 100);
        $result = new AnalysisResult([
            new FileAnalysisResult('a.php', [new Finding(Severity::High, 1, $sql, 'reason')]),
        ]);

        $output = (new TextReporter())->render($result);
        $line = array_values(array_filter(explode("\n", $output), static fn (string $line): bool => str_contains($line, 'UPDATE demo')))[0];

        self::assertStringContainsString('...', $line);
    }
}
