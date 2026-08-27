<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Input;

final class InputIssue
{
    public function __construct(
        public readonly string $path,
        public readonly string $reason,
    ) {
    }
}
