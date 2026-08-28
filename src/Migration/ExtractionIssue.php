<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Migration;

use InvalidArgumentException;

final class ExtractionIssue
{
    public function __construct(
        public readonly ?int $line,
        public readonly string $reason,
    ) {
        if (null !== $line && $line < 1) {
            throw new InvalidArgumentException('Extraction issue line must be null or a positive integer.');
        }
    }
}
