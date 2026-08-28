<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Unit\Analysis;

use AlkinBG\DoctrineMigrationGuard\Analysis\Severity;
use AlkinBG\DoctrineMigrationGuard\Analysis\SqlLexicalScanner;
use AlkinBG\DoctrineMigrationGuard\Analysis\SqlRiskAnalyzer;
use AlkinBG\DoctrineMigrationGuard\Migration\ExtractedStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqlRiskAnalyzerTest extends TestCase
{
    #[DataProvider('riskCases')]
    public function testClassifiesDocumentedRules(string $sql, Severity $severity): void
    {
        $finding = (new SqlRiskAnalyzer(new SqlLexicalScanner()))->analyze(new ExtractedStatement($sql, 42));

        self::assertSame($severity, $finding->severity, $sql);
        self::assertSame(42, $finding->line);
        self::assertSame(trim($sql), $finding->sql);
        self::assertNotSame('', $finding->reason);
    }

    /** @return iterable<string, array{string, Severity}> */
    public static function riskCases(): iterable
    {
        yield 'create table' => ['CREATE TABLE users (id INT PRIMARY KEY, INDEX idx_id (id))', Severity::Info];
        yield 'add nullable column' => ['ALTER TABLE users ADD COLUMN nickname VARCHAR(64) NULL', Severity::Info];
        yield 'add implicitly nullable column' => ['ALTER TABLE `users` ADD nickname VARCHAR(64)', Severity::Info];
        yield 'add not null default' => [
            "ALTER TABLE users ADD status VARCHAR(16) NOT NULL DEFAULT 'active'",
            Severity::Warning
        ];
        yield 'add not null no default' => ['ALTER TABLE users ADD email VARCHAR(255) NOT NULL', Severity::High];
        yield 'add column inline primary key' => [
            'ALTER TABLE users ADD COLUMN id INT PRIMARY KEY',
            Severity::Unanalyzed,
        ];

        yield 'add column inline unique' => [
            'ALTER TABLE users ADD COLUMN email VARCHAR(255) UNIQUE',
            Severity::Unanalyzed,
        ];

        yield 'add column inline references' => [
            'ALTER TABLE users ADD COLUMN parent_id INT REFERENCES parents(id)',
            Severity::Unanalyzed,
        ];

        yield 'add column inline check' => [
            'ALTER TABLE users ADD COLUMN score INT CHECK (score >= 0)',
            Severity::Unanalyzed,
        ];
        yield 'drop column' => ['ALTER TABLE users DROP COLUMN legacy', Severity::Critical];
        yield 'drop backtick quoted column' => ['ALTER TABLE users DROP COLUMN `legacy`', Severity::Critical];
        yield 'drop table' => ['DROP TABLE IF EXISTS legacy_users', Severity::Critical];
        yield 'truncate' => ['TRUNCATE TABLE audit_log', Severity::Critical];
        yield 'modify column' => ['ALTER TABLE users MODIFY COLUMN email VARCHAR(320) NOT NULL', Severity::High];
        yield 'change column' => ['ALTER TABLE users CHANGE COLUMN old_name new_name VARCHAR(64)', Severity::High];
        yield 'alter column' => ['ALTER TABLE users ALTER COLUMN status SET DEFAULT 1', Severity::High];
        yield 'rename table' => ['RENAME TABLE users TO app_users', Severity::High];
        yield 'rename column' => ['ALTER TABLE users RENAME COLUMN name TO display_name', Severity::High];
        yield 'alter rename table' => ['ALTER TABLE users RENAME TO app_users', Severity::High];
        yield 'update bounded' => ['UPDATE users SET active=0 WHERE id=10', Severity::Warning];
        yield 'update unbounded' => ['UPDATE users SET active=0', Severity::High];
        yield 'update where only in string' => ["UPDATE users SET note='where'", Severity::High];
        yield 'update where only in subquery' => [
            'UPDATE users SET x=(SELECT x FROM source WHERE id=1)',
            Severity::High
        ];
        yield 'update tautological where true' => ['UPDATE users SET active = 0 WHERE TRUE', Severity::High];
        yield 'update tautological where equality' => ['UPDATE users SET active = 0 WHERE 1 = 1', Severity::High];
        yield 'delete bounded' => ['DELETE FROM users WHERE id=10', Severity::Warning];
        yield 'delete unbounded' => ['DELETE FROM users', Severity::Critical];
        yield 'delete tautological where true' => ['DELETE FROM users WHERE TRUE', Severity::Critical];
        yield 'delete tautological where equality' => ['DELETE FROM users WHERE 1 = 1', Severity::Critical];
        yield 'create index' => ['CREATE INDEX idx_email ON users (email)', Severity::Warning];
        yield 'add index' => ['ALTER TABLE users ADD INDEX idx_email (email)', Severity::Warning];
        yield 'create unique index' => ['CREATE UNIQUE INDEX uniq_email ON users (email)', Severity::High];
        yield 'add unique key' => ['ALTER TABLE users ADD UNIQUE KEY uniq_email (email)', Severity::High];
        yield 'drop standalone index' => ['DROP INDEX idx_email ON users', Severity::Warning];
        yield 'drop alter index' => ['ALTER TABLE users DROP INDEX idx_email', Severity::Warning];
        yield 'add foreign key' => [
            'ALTER TABLE orders ADD FOREIGN KEY (user_id) REFERENCES users(id)',
            Severity::Warning
        ];
        yield 'add named foreign key' => [
            'ALTER TABLE orders ADD CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id)',
            Severity::Warning
        ];
        yield 'drop foreign key' => ['ALTER TABLE orders DROP FOREIGN KEY fk_user', Severity::Warning];
        yield 'unknown dml' => ['INSERT INTO users(id) VALUES (1)', Severity::Unanalyzed];
        yield 'unsupported alter' => ['ALTER TABLE users DROP PRIMARY KEY', Severity::Unanalyzed];
        yield 'add primary key unsupported' => ['ALTER TABLE users ADD PRIMARY KEY (id)', Severity::Unanalyzed];
        yield 'multi action alter' => [
            'ALTER TABLE users ADD last_login_at DATETIME DEFAULT NULL, ADD last_seen_at DATETIME DEFAULT NULL',
            Severity::Unanalyzed
        ];
        yield 'mixed multi action alter' => [
            "ALTER TABLE news_posts ADD status VARCHAR(16) NOT NULL DEFAULT 'published', ADD author_id INT DEFAULT NULL",
            Severity::Unanalyzed
        ];
        yield 'multiple statements' => [
            'ALTER TABLE users ADD nickname VARCHAR(64) DEFAULT NULL; DROP TABLE audit_log;',
            Severity::Unanalyzed
        ];
        yield 'comma inside decimal is not multi action' => [
            'ALTER TABLE prices ADD amount DECIMAL(10,2) DEFAULT NULL',
            Severity::Info
        ];
        yield 'comma inside index list is not multi action' => [
            'ALTER TABLE users ADD INDEX idx_names (first_name,last_name)',
            Severity::Warning
        ];
        yield 'case whitespace variant' => [
            "  alter\n table users\n add column flag int not null default 0  ",
            Severity::Warning
        ];
    }

    public function testUnsupportedAlterHasExactFailClosedReason(): void
    {
        $finding = (new SqlRiskAnalyzer(new SqlLexicalScanner()))->analyze(
            new ExtractedStatement('ALTER TABLE users DROP PRIMARY KEY', 9),
        );

        self::assertSame(Severity::Unanalyzed, $finding->severity);
        self::assertSame('Unsupported ALTER TABLE operation.', $finding->reason);
    }

    public function testMultipleStatementsHaveExactFailClosedReason(): void
    {
        $finding = (new SqlRiskAnalyzer(new SqlLexicalScanner()))->analyze(
            new ExtractedStatement('ALTER TABLE users ADD x INT; DROP TABLE audit_log', 9),
        );

        self::assertSame(Severity::Unanalyzed, $finding->severity);
        self::assertSame('Multiple SQL statements in one addSql() call are not supported.', $finding->reason);
    }

    public function testEmptySqlFailsClosed(): void
    {
        $finding = (new SqlRiskAnalyzer(new SqlLexicalScanner()))->analyze(new ExtractedStatement('   ', 5));

        self::assertSame(Severity::Unanalyzed, $finding->severity);
        self::assertSame('SQL statement is empty.', $finding->reason);
    }
}
