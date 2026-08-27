<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Analysis;

enum Severity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case High = 'high';
    case Critical = 'critical';
    case Unanalyzed = 'unanalyzed';
}
