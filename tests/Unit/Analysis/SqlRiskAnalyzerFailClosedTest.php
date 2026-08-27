<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Unit\Analysis;

use AlkinBG\DoctrineMigrationGuard\Analysis\Severity;
use AlkinBG\DoctrineMigrationGuard\Analysis\SqlLexicalScanner;
use AlkinBG\DoctrineMigrationGuard\Analysis\SqlRiskAnalyzer;
use AlkinBG\DoctrineMigrationGuard\Migration\ExtractedStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqlRiskAnalyzerFailClosedTest extends TestCase
{
    #[DataProvider('regressions')]
    public function testFalseNegativeRegressionsFailClosed(string $sql, Severity $expected): void
    {
        $finding = (new SqlRiskAnalyzer(new SqlLexicalScanner()))->analyze(new ExtractedStatement($sql, 9));

        self::assertSame($expected, $finding->severity, $sql);
    }

    /** @return iterable<string, array{string, Severity}> */
    public static function regressions(): iterable
    {
        yield 'default token inside enum is not a default clause' => [
            "ALTER TABLE users ADD status ENUM('DEFAULT') NOT NULL",
            Severity::High,
        ];
        yield 'default token inside comment text is not a default clause' => [
            "ALTER TABLE users ADD status VARCHAR(32) NOT NULL COMMENT 'DEFAULT is assigned elsewhere'",
            Severity::High,
        ];
        yield 'comment between not and null does not hide not-null clause' => [
            'ALTER TABLE users ADD status VARCHAR(32) NOT /* review */ NULL',
            Severity::High,
        ];
        yield 'double dash arithmetic cannot hide a second statement' => [
            'ALTER TABLE users ADD score INT DEFAULT (1--1); DROP TABLE audit_log',
            Severity::Unanalyzed,
        ];
        yield 'mysql executable comment' => [
            'ALTER TABLE users ADD nickname VARCHAR(64) DEFAULT NULL /*! , DROP COLUMN legacy */',
            Severity::Unanalyzed,
        ];
        yield 'mariadb executable comment' => [
            'ALTER TABLE users ADD nickname VARCHAR(64) DEFAULT NULL /*M! , DROP COLUMN legacy */',
            Severity::Unanalyzed,
        ];
        yield 'unterminated string' => [
            "ALTER TABLE users ADD note VARCHAR(255) DEFAULT 'oops",
            Severity::Unanalyzed,
        ];
        yield 'unbalanced parenthesis' => [
            'ALTER TABLE users ADD amount DECIMAL(10,2',
            Severity::Unanalyzed,
        ];
    }

    public function testExecutableCommentReasonIsExplicit(): void
    {
        $finding = (new SqlRiskAnalyzer(new SqlLexicalScanner()))->analyze(
            new ExtractedStatement('ALTER TABLE users ADD x INT /*! , DROP COLUMN legacy */', 9),
        );

        self::assertSame('Executable MySQL/MariaDB comments are not supported.', $finding->reason);
    }

    public function testIncompleteLexicalStructureReasonIsExplicit(): void
    {
        $finding = (new SqlRiskAnalyzer(new SqlLexicalScanner()))->analyze(
            new ExtractedStatement("ALTER TABLE users ADD note VARCHAR(255) DEFAULT 'oops", 9),
        );

        self::assertSame('SQL contains an unterminated quote/comment or unbalanced parentheses.', $finding->reason);
    }
}
