<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Analysis;

use InvalidArgumentException;

final class Finding
{
    public function __construct(
        public readonly Severity $severity,
        public readonly ?int $line,
        public readonly ?string $sql,
        public readonly string $reason,
    ) {
        if (null !== $line && $line < 1) {
            throw new InvalidArgumentException('Finding line must be null or a positive integer.');
        }
    }
}
