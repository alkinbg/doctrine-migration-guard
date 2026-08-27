<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Unit\Analysis;

use AlkinBG\DoctrineMigrationGuard\Analysis\AnalysisResult;
use AlkinBG\DoctrineMigrationGuard\Analysis\FileAnalysisResult;
use AlkinBG\DoctrineMigrationGuard\Analysis\Finding;
use AlkinBG\DoctrineMigrationGuard\Analysis\ResultStatus;
use AlkinBG\DoctrineMigrationGuard\Analysis\Severity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AnalysisResultTest extends TestCase
{
    public function testSeverityValuesAreStable(): void
    {
        self::assertSame('info', Severity::Info->value);
        self::assertSame('warning', Severity::Warning->value);
        self::assertSame('high', Severity::High->value);
        self::assertSame('critical', Severity::Critical->value);
        self::assertSame('unanalyzed', Severity::Unanalyzed->value);
    }

    public function testResultExitCodesAreStable(): void
    {
        self::assertSame(0, ResultStatus::Passed->exitCode());
        self::assertSame(1, ResultStatus::Failed->exitCode());
        self::assertSame(2, ResultStatus::Incomplete->exitCode());
    }

    public function testFindingRejectsNonPositiveLine(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Finding(Severity::Info, 0, 'CREATE TABLE demo (id INT)', 'Created table.');
    }

    public function testInfoAndWarningOnlyPass(): void
    {
        $result = new AnalysisResult([
            new FileAnalysisResult('a.php', [
                new Finding(Severity::Info, 1, 'CREATE TABLE a (id INT)', 'info'),
                new Finding(Severity::Warning, 2, 'CREATE INDEX idx ON a(id)', 'warning'),
            ]),
        ]);

        self::assertSame(ResultStatus::Passed, $result->status());
        self::assertSame(0, $result->exitCode());
    }

    public function testHighOrCriticalFails(): void
    {
        self::assertSame(ResultStatus::Failed, (new FileAnalysisResult('a.php', [
            new Finding(Severity::High, 1, 'UPDATE a SET x=1', 'high'),
        ]))->status());
        self::assertSame(ResultStatus::Failed, (new FileAnalysisResult('b.php', [
            new Finding(Severity::Critical, 1, 'DROP TABLE b', 'critical'),
        ]))->status());
    }

    public function testUnanalyzedMakesResultIncompleteAndWinsPrecedence(): void
    {
        $result = new AnalysisResult([
            new FileAnalysisResult('a.php', [new Finding(Severity::Critical, 10, 'DROP TABLE a', 'critical')]),
            new FileAnalysisResult('b.php', [new Finding(Severity::Unanalyzed, null, null, 'unsupported')]),
        ]);

        self::assertSame(ResultStatus::Incomplete, $result->status());
        self::assertSame(2, $result->exitCode());
    }

    public function testEmptyAnalysisPasses(): void
    {
        $result = new AnalysisResult([]);

        self::assertSame(ResultStatus::Passed, $result->status());
        self::assertSame(0, $result->exitCode());
    }

    public function testSummaryAlwaysContainsAllSeverityKeys(): void
    {
        $result = new AnalysisResult([
            new FileAnalysisResult('a.php', [
                new Finding(Severity::Info, 1, 'A', 'a'),
                new Finding(Severity::Info, 2, 'B', 'b'),
                new Finding(Severity::High, 3, 'C', 'c'),
                new Finding(Severity::Unanalyzed, null, null, 'd'),
            ]),
        ]);

        self::assertSame([
            'info' => 2,
            'warning' => 0,
            'high' => 1,
            'critical' => 0,
            'unanalyzed' => 1,
        ], $result->summary());
    }
}
