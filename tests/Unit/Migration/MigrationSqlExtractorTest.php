<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Unit\Migration;

use AlkinBG\DoctrineMigrationGuard\Migration\MigrationExtraction;
use AlkinBG\DoctrineMigrationGuard\Migration\MigrationSqlExtractor;
use PHPUnit\Framework\TestCase;

final class MigrationSqlExtractorTest extends TestCase
{
    private MigrationSqlExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new MigrationSqlExtractor();
    }

    public function testExtractsOnlyStaticTopLevelAddSqlFromUp(): void
    {
        $extraction = $this->extractor->extract($this->fixture('StaticMigration.php'));

        self::assertSame([], $extraction->issues);
        self::assertSame([
            'CREATE TABLE demo (id INT)',
            'ALTER TABLE demo ADD nickname VARCHAR(64) DEFAULT NULL',
            "UPDATE demo SET touched = 1\nWHERE id = 1",
        ], array_map(static fn ($statement): string => $statement->sql, $extraction->statements));
        self::assertSame([14, 15, 16], array_map(static fn ($statement): int => $statement->line, $extraction->statements));
        self::assertStringNotContainsString('DROP TABLE demo', implode("\n", array_map(static fn ($statement): string => $statement->sql, $extraction->statements)));
    }

    public function testRecognizesFullyQualifiedAbstractMigrationParent(): void
    {
        $extraction = $this->extractor->extract($this->fixture('FqcnMigration.php'));

        self::assertSame([], $extraction->issues);
        self::assertCount(1, $extraction->statements);
        self::assertSame('CREATE TABLE fqcn_demo (id INT)', $extraction->statements[0]->sql);
    }

    public function testDynamicSqlFailsClosed(): void
    {
        $extraction = $this->extractor->extract($this->fixture('DynamicMigration.php'));

        self::assertNotEmpty($extraction->issues);
        self::assertContains('addSql() SQL argument is not statically resolvable.', $this->reasons($extraction));
    }

    public function testConditionalAddSqlFailsClosed(): void
    {
        $extraction = $this->extractor->extract($this->fixture('ConditionalMigration.php'));

        self::assertSame(['Unsupported executable statement in up().'], $this->reasons($extraction));
    }

    public function testConnectionExecutionFailsClosed(): void
    {
        $extraction = $this->extractor->extract($this->fixture('ConnectionMigration.php'));

        self::assertSame(['Unsupported executable statement in up().'], $this->reasons($extraction));
    }

    public function testSchemaApiFailsClosed(): void
    {
        $extraction = $this->extractor->extract($this->fixture('SchemaApiMigration.php'));

        self::assertSame(['Unsupported executable statement in up().'], $this->reasons($extraction));
    }

    public function testSupportedSqlIsStillReportedBesideUnsupportedCode(): void
    {
        $extraction = $this->extractor->extract($this->fixture('MixedMigration.php'));

        self::assertCount(1, $extraction->statements);
        self::assertSame('CREATE TABLE mixed_demo (id INT)', $extraction->statements[0]->sql);
        self::assertSame(['Unsupported executable statement in up().'], $this->reasons($extraction));
    }

    public function testEmptyUpIsValidNoOp(): void
    {
        $extraction = $this->extractor->extract($this->fixture('EmptyUpMigration.php'));

        self::assertSame([], $extraction->statements);
        self::assertSame([], $extraction->issues);
    }

    public function testMissingUpFailsClosed(): void
    {
        $extraction = $this->extractor->extract($this->fixture('NoUpMigration.php'));

        self::assertSame(['Doctrine migration does not define up().'], $this->reasons($extraction));
    }

    public function testNonDoctrinePhpFileFailsClosed(): void
    {
        $extraction = $this->extractor->extract($this->fixture('NotDoctrineMigration.php'));

        self::assertSame(['File does not contain a Doctrine AbstractMigration subclass.'], $this->reasons($extraction));
    }

    public function testMultipleDoctrineMigrationClassesFailClosed(): void
    {
        $extraction = $this->extractor->extract($this->fixture('MultipleMigrationClasses.php'));

        self::assertSame(['File contains multiple Doctrine migration classes.'], $this->reasons($extraction));
    }

    public function testMalformedPhpBecomesParseIssue(): void
    {
        $extraction = $this->extractor->extract($this->fixture('MalformedMigration.php'));

        self::assertCount(1, $extraction->issues);
        self::assertStringStartsWith('PHP parse error:', $extraction->issues[0]->reason);
        self::assertNotNull($extraction->issues[0]->line);
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__, 2).'/Fixtures/Migrations/'.$name;
    }

    /** @return list<string> */
    private function reasons(MigrationExtraction $extraction): array
    {
        return array_map(static fn ($issue): string => $issue->reason, $extraction->issues);
    }
}
