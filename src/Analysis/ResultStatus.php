<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Analysis;

enum ResultStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Incomplete = 'incomplete';

    public function exitCode(): int
    {
        return match ($this) {
            self::Passed => 0,
            self::Failed => 1,
            self::Incomplete => 2,
        };
    }
}
