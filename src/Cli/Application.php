<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Cli;

use AlkinBG\DoctrineMigrationGuard\Analysis\SqlLexicalScanner;
use AlkinBG\DoctrineMigrationGuard\Analysis\SqlRiskAnalyzer;
use AlkinBG\DoctrineMigrationGuard\Input\InputResolver;
use AlkinBG\DoctrineMigrationGuard\Migration\MigrationSqlExtractor;
use AlkinBG\DoctrineMigrationGuard\Reporting\JsonReporter;
use AlkinBG\DoctrineMigrationGuard\Reporting\TextReporter;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    /**
     * @throws ReflectionException
     */
    public function __construct()
    {
        parent::__construct('Doctrine Migration Guard');

        $scanner = new SqlLexicalScanner();
        $command = new AnalyzeCommand(
            new InputResolver(),
            new MigrationSqlExtractor(),
            new SqlRiskAnalyzer($scanner),
            new TextReporter(),
            new JsonReporter(),
        );

        $this->registerCommandCompatibly($command);
        $this->setDefaultCommand('analyze', true);
    }

    /**
     * @throws ReflectionException
     */
    private function registerCommandCompatibly(AnalyzeCommand $command): void
    {
        $application = new ReflectionClass(SymfonyApplication::class);
        $method = $application->hasMethod('addCommand')
            ? $application->getMethod('addCommand')
            : $application->getMethod('add');

        $method->invoke($this, $command);
    }
}
