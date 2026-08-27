<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Input;

final class InputResolution
{
    /**
     * @param list<string> $files
     * @param list<InputIssue> $issues
     */
    public function __construct(
        public readonly array $files,
        public readonly array $issues,
    ) {
    }
}
