<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Analysis;

final class AnalysisResult
{
    /** @param list<FileAnalysisResult> $files */
    public function __construct(public readonly array $files)
    {
    }

    public function status(): ResultStatus
    {
        foreach ($this->files as $file) {
            if ($file->status() === ResultStatus::Incomplete) {
                return ResultStatus::Incomplete;
            }
        }

        foreach ($this->files as $file) {
            if ($file->status() === ResultStatus::Failed) {
                return ResultStatus::Failed;
            }
        }

        return ResultStatus::Passed;
    }

    public function exitCode(): int
    {
        return $this->status()->exitCode();
    }

    /** @return array{info: int, warning: int, high: int, critical: int, unanalyzed: int} */
    public function summary(): array
    {
        $summary = [
            'info' => 0,
            'warning' => 0,
            'high' => 0,
            'critical' => 0,
            'unanalyzed' => 0,
        ];

        foreach ($this->files as $file) {
            foreach ($file->findings as $finding) {
                ++$summary[$finding->severity->value];
            }
        }

        return $summary;
    }
}
