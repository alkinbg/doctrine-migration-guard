<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Unit\Analysis;

use AlkinBG\DoctrineMigrationGuard\Analysis\Severity;
use AlkinBG\DoctrineMigrationGuard\Analysis\SqlLexicalScanner;
use AlkinBG\DoctrineMigrationGuard\Analysis\SqlRiskAnalyzer;
use AlkinBG\DoctrineMigrationGuard\Migration\ExtractedStatement;
use PHPUnit\Framework\TestCase;

final class SqlModeAmbiguityTest extends TestCase
{
    public function testBackslashEscapeInsideStringIsDetectedAsSqlModeDependent(): void
    {
        $sql = "UPDATE users SET note='it\\'s reviewed' WHERE id=1";

        self::assertTrue((new SqlLexicalScanner())->hasModeDependentBackslashEscape($sql));
    }

    public function testBackslashOutsideQuotedStringIsNotMarkedModeDependent(): void
    {
        self::assertFalse((new SqlLexicalScanner())->hasModeDependentBackslashEscape('SELECT path\\name FROM users'));
    }

    public function testAnalyzerFailsClosedOnSqlModeDependentBackslashEscapes(): void
    {
        $sql = "ALTER TABLE users ADD note VARCHAR(255) DEFAULT 'it\\'s reviewed'";
        $finding = (new SqlRiskAnalyzer(new SqlLexicalScanner()))->analyze(new ExtractedStatement($sql, 12));

        self::assertSame(Severity::Unanalyzed, $finding->severity);
        self::assertSame('Backslash escapes in quoted SQL depend on SQL mode and are not supported.', $finding->reason);
    }
}
