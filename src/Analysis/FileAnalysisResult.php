<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Analysis;

final class FileAnalysisResult
{
    /** @param list<Finding> $findings */
    public function __construct(
        public readonly string $path,
        public readonly array $findings,
    ) {
    }

    public function status(): ResultStatus
    {
        foreach ($this->findings as $finding) {
            if ($finding->severity === Severity::Unanalyzed) {
                return ResultStatus::Incomplete;
            }
        }

        foreach ($this->findings as $finding) {
            if ($finding->severity === Severity::High || $finding->severity === Severity::Critical) {
                return ResultStatus::Failed;
            }
        }

        return ResultStatus::Passed;
    }
}
