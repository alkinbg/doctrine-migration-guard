<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Unit\Analysis;

use AlkinBG\DoctrineMigrationGuard\Analysis\SqlLexicalScanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqlLexicalScannerTest extends TestCase
{
    #[DataProvider('whereCases')]
    public function testDetectsOnlyTopLevelWhere(string $sql, bool $expected): void
    {
        self::assertSame($expected, (new SqlLexicalScanner())->hasTopLevelKeyword($sql, 'WHERE'));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function whereCases(): iterable
    {
        yield 'quoted keyword' => ["UPDATE users SET name='where'", false];
        yield 'nested subquery' => ['UPDATE users SET x=(SELECT x FROM t WHERE id=1)', false];
        yield 'top level where' => ['UPDATE users SET x=1 WHERE id=1', true];
        yield 'backtick keyword' => ['UPDATE users SET `where` = 1', false];
        yield 'block comment keyword' => ['UPDATE users SET x=1 /* WHERE id=1 */', false];
        yield 'dash comment keyword' => ["UPDATE users SET x=1 -- WHERE id=1\n", false];
        yield 'hash comment keyword' => ["UPDATE users SET x=1 # WHERE id=1\n", false];
        yield 'escaped quote then keyword in literal' => ["UPDATE users SET x='it\\'s WHERE hidden'", false];
        yield 'doubled quote then keyword in literal' => ["UPDATE users SET x='it''s WHERE hidden'", false];
        yield 'identifier boundary' => ['UPDATE users SET somewhere=1', false];
        yield 'double dash without whitespace is arithmetic' => ['UPDATE users SET balance=balance--1 WHERE id=1', true];
    }

    #[DataProvider('commaCases')]
    public function testDetectsOnlyTopLevelComma(string $sql, bool $expected): void
    {
        self::assertSame($expected, (new SqlLexicalScanner())->hasTopLevelComma($sql));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function commaCases(): iterable
    {
        yield 'type parameters' => ['DECIMAL(10,2)', false];
        yield 'index column list' => ['ADD INDEX idx (a,b)', false];
        yield 'quoted comma' => ["ADD note VARCHAR(100) DEFAULT 'a,b'", false];
        yield 'comment comma' => ['ADD a INT /* , DROP b */', false];
        yield 'multiple alter actions' => ['ADD a INT, DROP COLUMN b', true];
    }

    #[DataProvider('statementCases')]
    public function testDetectsMultipleTopLevelStatements(string $sql, bool $expected): void
    {
        self::assertSame($expected, (new SqlLexicalScanner())->hasMultipleTopLevelStatements($sql));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function statementCases(): iterable
    {
        yield 'semicolon in string' => ["SELECT 'a;b'", false];
        yield 'single trailing semicolon' => ['SELECT 1;', false];
        yield 'trailing comment' => ['SELECT 1; /* comment */', false];
        yield 'two statements' => ['SELECT 1; DROP TABLE demo', true];
        yield 'semicolon in block comment' => ['SELECT 1 /* ; */', false];
        yield 'semicolon in line comment' => ["SELECT 1 -- ; DROP TABLE demo\n", false];
        yield 'semicolon in backtick' => ['SELECT `a;b` FROM demo', false];
        yield 'second statement after comment' => ['SELECT 1; /* comment */ DROP TABLE demo', true];
        yield 'double dash arithmetic before second statement' => ['SELECT 1--1; DROP TABLE demo', true];
    }

    #[DataProvider('lexicalCompletenessCases')]
    public function testDetectsLexicallyIncompleteSql(string $sql, bool $expected): void
    {
        self::assertSame($expected, (new SqlLexicalScanner())->isLexicallyComplete($sql));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function lexicalCompletenessCases(): iterable
    {
        yield 'balanced sql' => ['ALTER TABLE users ADD amount DECIMAL(10,2)', true];
        yield 'unterminated single quote' => ["ALTER TABLE users ADD note VARCHAR(255) DEFAULT 'oops", false];
        yield 'unterminated double quote' => ['ALTER TABLE users ADD note VARCHAR(255) DEFAULT "oops', false];
        yield 'unterminated backtick' => ['ALTER TABLE `users ADD note VARCHAR(255)', false];
        yield 'unterminated block comment' => ['ALTER TABLE users ADD note VARCHAR(255) /* comment', false];
        yield 'unclosed parenthesis' => ['ALTER TABLE users ADD amount DECIMAL(10,2', false];
        yield 'extra closing parenthesis' => ['ALTER TABLE users ADD amount INT)', false];
        yield 'line comment at eof is complete' => ['SELECT 1 -- comment', true];
    }

    #[DataProvider('executableCommentCases')]
    public function testDetectsMysqlAndMariaDbExecutableComments(string $sql, bool $expected): void
    {
        self::assertSame($expected, (new SqlLexicalScanner())->hasExecutableComment($sql));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function executableCommentCases(): iterable
    {
        yield 'mysql executable comment' => ['ALTER TABLE users ADD x INT /*! , DROP COLUMN legacy */', true];
        yield 'mariadb executable comment' => ['ALTER TABLE users ADD x INT /*M! , DROP COLUMN legacy */', true];
        yield 'normal block comment' => ['ALTER TABLE users ADD x INT /* review */', false];
        yield 'mysql marker inside string' => ["UPDATE users SET note='/*! not executable */' WHERE id=1", false];
        yield 'mariadb marker inside string' => ["UPDATE users SET note='/*M! not executable */' WHERE id=1", false];
    }
}
