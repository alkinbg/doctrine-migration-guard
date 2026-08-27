<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Migration;

final class MigrationExtraction
{
    /**
     * @param list<ExtractedStatement> $statements
     * @param list<ExtractionIssue> $issues
     */
    public function __construct(
        public readonly array $statements,
        public readonly array $issues,
    ) {
    }
}
