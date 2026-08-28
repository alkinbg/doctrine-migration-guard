<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Cli;

use AlkinBG\DoctrineMigrationGuard\Analysis\AnalysisResult;
use AlkinBG\DoctrineMigrationGuard\Analysis\FileAnalysisResult;
use AlkinBG\DoctrineMigrationGuard\Analysis\Finding;
use AlkinBG\DoctrineMigrationGuard\Analysis\Severity;
use AlkinBG\DoctrineMigrationGuard\Analysis\SqlRiskAnalyzer;
use AlkinBG\DoctrineMigrationGuard\Input\InputResolver;
use AlkinBG\DoctrineMigrationGuard\Migration\MigrationSqlExtractor;
use AlkinBG\DoctrineMigrationGuard\Reporting\JsonReporter;
use AlkinBG\DoctrineMigrationGuard\Reporting\TextReporter;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class AnalyzeCommand extends Command
{
    public function __construct(
        private readonly InputResolver $inputResolver,
        private readonly MigrationSqlExtractor $extractor,
        private readonly SqlRiskAnalyzer $analyzer,
        private readonly TextReporter $textReporter,
        private readonly JsonReporter $jsonReporter,
    ) {
        parent::__construct('analyze');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Analyze Doctrine migration files for risky MySQL/MariaDB operations.')
            ->addArgument('paths', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Migration files or directories')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rawPaths = $input->getArgument('paths');
        if (!is_array($rawPaths)) {
            throw new LogicException('Migration paths must be provided as an array.');
        }

        /** @var list<string> $paths */
        $paths = [];
        foreach ($rawPaths as $path) {
            if (!is_string($path)) {
                throw new LogicException('Every migration path must be a string.');
            }
            $paths[] = $path;
        }

        $format = $input->getOption('format');
        if ('text' !== $format && 'json' !== $format) {
            throw new InvalidArgumentException('Unsupported format. Expected text or json.');
        }

        $resolution = $this->inputResolver->resolve($paths);
        /** @var list<FileAnalysisResult> $fileResults */
        $fileResults = [];

        foreach ($resolution->issues as $issue) {
            $fileResults[] = new FileAnalysisResult($issue->path, [
                new Finding(Severity::Unanalyzed, null, null, $issue->reason),
            ]);
        }

        foreach ($resolution->files as $path) {
            $extraction = $this->extractor->extract($path);
            /** @var list<Finding> $findings */
            $findings = [];

            foreach ($extraction->statements as $statement) {
                $findings[] = $this->analyzer->analyze($statement);
            }

            foreach ($extraction->issues as $issue) {
                $findings[] = new Finding(Severity::Unanalyzed, $issue->line, null, $issue->reason);
            }

            $fileResults[] = new FileAnalysisResult($path, $findings);
        }

        $analysis = new AnalysisResult($fileResults);
        $rendered = 'json' === $format
            ? $this->jsonReporter->render($analysis)
            : $this->textReporter->render($analysis);

        $output->write($rendered);

        return $analysis->exitCode();
    }
}
