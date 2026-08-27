<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Reporting;

use AlkinBG\DoctrineMigrationGuard\Analysis\AnalysisResult;

final class TextReporter
{
    private const SQL_MAX_LENGTH = 180;

    public function render(AnalysisResult $result): string
    {
        $output = "Migration Guard\n\n";

        foreach ($result->files as $file) {
            $output .= $file->path."\n";

            foreach ($file->findings as $finding) {
                $severity = strtoupper($finding->severity->value);
                $line = $finding->line !== null ? (string) $finding->line : '-';
                $sql = $this->compactSql($finding->sql);
                $output .= sprintf("  %-10s line %-4s %s\n", $severity, $line, $sql);
                $output .= '             '.$finding->reason."\n";
            }

            $output .= "\n";
        }

        $summary = $result->summary();
        $output .= "Summary\n";
        $output .= sprintf("  INFO:       %d\n", $summary['info']);
        $output .= sprintf("  WARNING:    %d\n", $summary['warning']);
        $output .= sprintf("  HIGH:       %d\n", $summary['high']);
        $output .= sprintf("  CRITICAL:   %d\n", $summary['critical']);
        $output .= sprintf("  UNANALYZED: %d\n", $summary['unanalyzed']);
        $output .= "\nResult: ".strtoupper($result->status()->value)."\n";

        return $output;
    }

    private function compactSql(?string $sql): string
    {
        if ($sql === null) {
            return 'SQL unavailable';
        }

        $compact = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
        if (strlen($compact) > self::SQL_MAX_LENGTH) {
            return substr($compact, 0, self::SQL_MAX_LENGTH - 3).'...';
        }

        return $compact;
    }
}
