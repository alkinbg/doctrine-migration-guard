<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Input;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class InputResolver
{
    /**
     * @param list<string> $inputs
     */
    public function resolve(array $inputs): InputResolution
    {
        /** @var array<string, string> $files */
        $files = [];
        /** @var list<InputIssue> $issues */
        $issues = [];

        foreach ($inputs as $input) {
            if (!file_exists($input)) {
                $issues[] = new InputIssue($input, 'Path does not exist.');
                continue;
            }

            if (is_file($input)) {
                if (!is_readable($input)) {
                    $issues[] = new InputIssue($input, 'File is not readable.');
                    continue;
                }

                if ('php' !== strtolower(pathinfo($input, PATHINFO_EXTENSION))) {
                    $issues[] = new InputIssue($input, 'Input file is not a PHP file.');
                    continue;
                }

                $files[$this->dedupeKey($input)] = $this->normalizePath($input);
                continue;
            }

            if (!is_dir($input)) {
                $issues[] = new InputIssue($input, 'Input is neither a file nor a directory.');
                continue;
            }

            $foundPhpFile = false;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($input, FilesystemIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $fileInfo */
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile() || 'php' !== strtolower($fileInfo->getExtension())) {
                    continue;
                }

                $foundPhpFile = true;
                $path = $fileInfo->getPathname();

                if (!is_readable($path)) {
                    $issues[] = new InputIssue($this->normalizePath($path), 'File is not readable.');
                    continue;
                }

                $files[$this->dedupeKey($path)] = $this->normalizePath($path);
            }

            if (!$foundPhpFile) {
                $issues[] = new InputIssue($input, 'Directory contains no PHP files.');
            }
        }

        $resolvedFiles = array_values($files);
        sort($resolvedFiles, SORT_STRING);

        return new InputResolution($resolvedFiles, $issues);
    }

    private function dedupeKey(string $path): string
    {
        $realPath = realpath($path);

        return $this->normalizePath(false !== $realPath ? $realPath : $path);
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
