<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Unit\Reporting;

use AlkinBG\DoctrineMigrationGuard\Analysis\AnalysisResult;
use AlkinBG\DoctrineMigrationGuard\Analysis\FileAnalysisResult;
use AlkinBG\DoctrineMigrationGuard\Analysis\Finding;
use AlkinBG\DoctrineMigrationGuard\Analysis\Severity;
use AlkinBG\DoctrineMigrationGuard\Reporting\JsonReporter;
use PHPUnit\Framework\TestCase;

final class JsonReporterTest extends TestCase
{
    public function testRendersVersionedStableJsonSchema(): void
    {
        $result = new AnalysisResult([
            new FileAnalysisResult('migrations/A.php', [
                new Finding(Severity::High, 25, 'UPDATE users SET active=0', 'Unbounded update.'),
            ]),
            new FileAnalysisResult('migrations/B.php', [
                new Finding(Severity::Unanalyzed, null, null, 'Unsupported migration code.'),
            ]),
        ]);

        $json = (new JsonReporter())->render($result);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        /** @var array<string, mixed> $payload */
        $payload = $decoded;

        self::assertSame(['schema_version', 'result', 'files', 'summary'], array_keys($payload));
        self::assertSame(1, $payload['schema_version']);
        self::assertSame('incomplete', $payload['result']);

        $files = $payload['files'];
        self::assertIsArray($files);
        self::assertCount(2, $files);

        $firstFile = $files[0];
        $secondFile = $files[1];
        self::assertIsArray($firstFile);
        self::assertIsArray($secondFile);
        self::assertSame(['path', 'result', 'findings'], array_keys($firstFile));

        $firstFindings = $firstFile['findings'];
        $secondFindings = $secondFile['findings'];
        self::assertIsArray($firstFindings);
        self::assertIsArray($secondFindings);
        self::assertCount(1, $firstFindings);
        self::assertCount(1, $secondFindings);

        $firstFinding = $firstFindings[0];
        $secondFinding = $secondFindings[0];
        self::assertIsArray($firstFinding);
        self::assertIsArray($secondFinding);
        self::assertSame(['severity', 'line', 'sql', 'reason'], array_keys($firstFinding));
        self::assertSame('high', $firstFinding['severity']);
        self::assertSame(25, $firstFinding['line']);
        self::assertNull($secondFinding['line']);
        self::assertNull($secondFinding['sql']);

        self::assertSame([
            'info' => 0,
            'warning' => 0,
            'high' => 1,
            'critical' => 0,
            'unanalyzed' => 1,
        ], $payload['summary']);
        self::assertStringNotContainsString("\e[", $json);
        self::assertStringEndsWith("\n", $json);
    }
}
