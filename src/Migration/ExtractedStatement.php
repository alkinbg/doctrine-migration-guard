<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Migration;

use InvalidArgumentException;

final class ExtractedStatement
{
    public function __construct(
        public readonly string $sql,
        public readonly int $line,
    ) {
        if ($line < 1) {
            throw new InvalidArgumentException('Extracted statement line must be a positive integer.');
        }
    }
}
