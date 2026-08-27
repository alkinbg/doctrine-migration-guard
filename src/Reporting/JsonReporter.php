<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Reporting;

use AlkinBG\DoctrineMigrationGuard\Analysis\AnalysisResult;

final class JsonReporter
{
    public function render(AnalysisResult $result): string
    {
        $files = [];

        foreach ($result->files as $file) {
            $findings = [];
            foreach ($file->findings as $finding) {
                $findings[] = [
                    'severity' => $finding->severity->value,
                    'line' => $finding->line,
                    'sql' => $finding->sql,
                    'reason' => $finding->reason,
                ];
            }

            $files[] = [
                'path' => $file->path,
                'result' => $file->status()->value,
                'findings' => $findings,
            ];
        }

        $payload = [
            'schema_version' => 1,
            'result' => $result->status()->value,
            'files' => $files,
            'summary' => $result->summary(),
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
}
