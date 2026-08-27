<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Tests\Unit\Input;

use AlkinBG\DoctrineMigrationGuard\Input\InputResolver;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class InputResolverTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/doctrine-migration-guard-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            self::assertInstanceOf(SplFileInfo::class, $item);
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->root);
    }

    public function testResolvesOnePhpFile(): void
    {
        $file = $this->root.'/Version1.php';
        file_put_contents($file, '<?php');

        $result = (new InputResolver())->resolve([$file]);

        self::assertSame([$file], $result->files);
        self::assertSame([], $result->issues);
    }

    public function testRecursesSortsAndIgnoresNonPhpFiles(): void
    {
        mkdir($this->root.'/nested');
        file_put_contents($this->root.'/Z.php', '<?php');
        file_put_contents($this->root.'/A.php', '<?php');
        file_put_contents($this->root.'/nested/B.php', '<?php');
        file_put_contents($this->root.'/nested/readme.txt', 'ignore');

        $result = (new InputResolver())->resolve([$this->root]);

        self::assertSame([
            $this->root.'/A.php',
            $this->root.'/Z.php',
            $this->root.'/nested/B.php',
        ], $result->files);
        self::assertSame([], $result->issues);
    }

    public function testDeduplicatesFilePassedDirectlyAndThroughDirectory(): void
    {
        $file = $this->root.'/Version1.php';
        file_put_contents($file, '<?php');

        $result = (new InputResolver())->resolve([$file, $this->root]);

        self::assertSame([$file], $result->files);
        self::assertSame([], $result->issues);
    }

    public function testMissingPathBecomesIssue(): void
    {
        $missing = $this->root.'/missing.php';

        $result = (new InputResolver())->resolve([$missing]);

        self::assertSame([], $result->files);
        self::assertCount(1, $result->issues);
        self::assertSame($missing, $result->issues[0]->path);
        self::assertSame('Path does not exist.', $result->issues[0]->reason);
    }

    public function testEmptyDirectoryBecomesIssue(): void
    {
        $empty = $this->root.'/empty';
        mkdir($empty);

        $result = (new InputResolver())->resolve([$empty]);

        self::assertSame([], $result->files);
        self::assertCount(1, $result->issues);
        self::assertSame('Directory contains no PHP files.', $result->issues[0]->reason);
    }

    public function testMixedValidAndInvalidInputsKeepValidFiles(): void
    {
        $valid = $this->root.'/Version1.php';
        $missing = $this->root.'/missing.php';
        file_put_contents($valid, '<?php');

        $result = (new InputResolver())->resolve([$valid, $missing]);

        self::assertSame([$valid], $result->files);
        self::assertCount(1, $result->issues);
        self::assertSame($missing, $result->issues[0]->path);
    }

    public function testRejectsExplicitNonPhpFile(): void
    {
        $file = $this->root.'/migration.sql';
        file_put_contents($file, 'SELECT 1');

        $result = (new InputResolver())->resolve([$file]);

        self::assertSame([], $result->files);
        self::assertCount(1, $result->issues);
        self::assertSame('Input file is not a PHP file.', $result->issues[0]->reason);
    }
}
