<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Analysis;

use AlkinBG\DoctrineMigrationGuard\Migration\ExtractedStatement;

final class SqlRiskAnalyzer
{
    private const IDENTIFIER = '(?:`(?:``|[^`])+`|[A-Za-z_][A-Za-z0-9_$]*)';
    private const TABLE_IDENTIFIER = '(?:(?:`(?:``|[^`])+`)(?:\s*\.\s*`(?:``|[^`])+`)?|[A-Za-z_][A-Za-z0-9_$.]*)';

    public function __construct(private readonly SqlLexicalScanner $scanner)
    {
    }

    public function analyze(ExtractedStatement $statement): Finding
    {
        $sql = trim($statement->sql);

        if ($sql === '') {
            return $this->finding($statement, Severity::Unanalyzed, $sql, 'SQL statement is empty.');
        }

        if ($this->scanner->hasModeDependentBackslashEscape($sql)) {
            return $this->finding(
                $statement,
                Severity::Unanalyzed,
                $sql,
                'Backslash escapes in quoted SQL depend on SQL mode and are not supported.',
            );
        }

        if (!$this->scanner->isLexicallyComplete($sql)) {
            return $this->finding(
                $statement,
                Severity::Unanalyzed,
                $sql,
                'SQL contains an unterminated quote/comment or unbalanced parentheses.',
            );
        }

        if ($this->scanner->hasExecutableComment($sql)) {
            return $this->finding(
                $statement,
                Severity::Unanalyzed,
                $sql,
                'Executable MySQL/MariaDB comments are not supported.',
            );
        }

        if ($this->scanner->hasMultipleTopLevelStatements($sql)) {
            return $this->finding(
                $statement,
                Severity::Unanalyzed,
                $sql,
                'Multiple SQL statements in one addSql() call are not supported.',
            );
        }

        if (preg_match('/^ALTER\s+TABLE\b/i', $sql) === 1) {
            return $this->analyzeAlterTable($statement, $sql);
        }

        if (preg_match('/^CREATE\s+TABLE\b/i', $sql) === 1) {
            return $this->finding($statement, Severity::Info, $sql, 'Creating a new table is recognized by the static ruleset.');
        }

        if (preg_match('/^DROP\s+TABLE\b/i', $sql) === 1) {
            return $this->finding($statement, Severity::Critical, $sql, 'Dropping a table permanently removes its stored data.');
        }

        if (preg_match('/^TRUNCATE(?:\s+TABLE)?\b/i', $sql) === 1) {
            return $this->finding($statement, Severity::Critical, $sql, 'Truncating a table removes all stored rows.');
        }

        if (preg_match('/^RENAME\s+TABLE\b/i', $sql) === 1) {
            return $this->finding($statement, Severity::High, $sql, 'Renaming a table can break code that still references the old name.');
        }

        if (preg_match('/^CREATE\s+UNIQUE\s+INDEX\b/i', $sql) === 1) {
            return $this->finding($statement, Severity::High, $sql, 'Creating a unique index can fail when existing rows contain duplicate values.');
        }

        if (preg_match('/^CREATE\s+INDEX\b/i', $sql) === 1) {
            return $this->finding($statement, Severity::Warning, $sql, 'Creating an index can lock or impact a large existing table.');
        }

        if (preg_match('/^DROP\s+INDEX\b/i', $sql) === 1) {
            return $this->finding($statement, Severity::Warning, $sql, 'Dropping an index can change query performance.');
        }

        if (preg_match('/^UPDATE\b/i', $sql) === 1) {
            if ($this->scanner->hasTopLevelKeyword($sql, 'WHERE')) {
                return $this->finding($statement, Severity::Warning, $sql, 'A bounded UPDATE changes existing rows and deserves review.');
            }

            return $this->finding($statement, Severity::High, $sql, 'An UPDATE without a top-level WHERE can modify every row in the target table.');
        }

        if (preg_match('/^DELETE\b/i', $sql) === 1) {
            if ($this->scanner->hasTopLevelKeyword($sql, 'WHERE')) {
                return $this->finding($statement, Severity::Warning, $sql, 'A bounded DELETE removes existing rows and deserves review.');
            }

            return $this->finding($statement, Severity::Critical, $sql, 'A DELETE without a top-level WHERE can remove every row in the target table.');
        }

        return $this->finding($statement, Severity::Unanalyzed, $sql, 'Unsupported SQL operation.');
    }

    private function analyzeAlterTable(ExtractedStatement $statement, string $sql): Finding
    {
        $pattern = '/^ALTER\s+TABLE\s+'.self::TABLE_IDENTIFIER.'\s+(?<action>.+)$/is';
        if (preg_match($pattern, $sql, $matches) !== 1) {
            return $this->finding($statement, Severity::Unanalyzed, $sql, 'Unsupported ALTER TABLE operation.');
        }

        $action = trim($matches['action']);
        if ($this->scanner->hasTopLevelComma($action)) {
            return $this->finding(
                $statement,
                Severity::Unanalyzed,
                $sql,
                'Multiple ALTER TABLE actions in one statement are not supported.',
            );
        }

        if (preg_match('/^DROP\s+COLUMN\s+'.self::IDENTIFIER.'\b/i', $action) === 1) {
            return $this->finding($statement, Severity::Critical, $sql, 'Dropping a column permanently removes stored data.');
        }

        if (preg_match('/^DROP\s+FOREIGN\s+KEY\b/i', $action) === 1) {
            return $this->finding($statement, Severity::Warning, $sql, 'Dropping a foreign key removes an existing referential constraint.');
        }

        if (preg_match('/^DROP\s+(?:INDEX|KEY)\b/i', $action) === 1) {
            return $this->finding($statement, Severity::Warning, $sql, 'Dropping an index can change query performance.');
        }

        if (preg_match('/^ADD\s+(?:CONSTRAINT\s+'.self::IDENTIFIER.'\s+)?FOREIGN\s+KEY\b/i', $action) === 1) {
            return $this->finding($statement, Severity::Warning, $sql, 'Adding a foreign key can validate or constrain existing rows.');
        }

        if (preg_match('/^ADD\s+UNIQUE\s+(?:INDEX|KEY)\b/i', $action) === 1) {
            return $this->finding($statement, Severity::High, $sql, 'Adding a unique key can fail when existing rows contain duplicate values.');
        }

        if (preg_match('/^ADD\s+(?:INDEX|KEY)\b/i', $action) === 1) {
            return $this->finding($statement, Severity::Warning, $sql, 'Creating an index can lock or impact a large existing table.');
        }

        if (preg_match('/^RENAME\s+COLUMN\b/i', $action) === 1) {
            return $this->finding($statement, Severity::High, $sql, 'Renaming a column can break code that still references the old name.');
        }

        if (preg_match('/^RENAME\s+(?:TO|AS)\b/i', $action) === 1) {
            return $this->finding($statement, Severity::High, $sql, 'Renaming a table can break code that still references the old name.');
        }

        if (preg_match('/^(?:MODIFY(?:\s+COLUMN)?|CHANGE(?:\s+COLUMN)?|ALTER\s+COLUMN)\b/i', $action) === 1) {
            return $this->finding($statement, Severity::High, $sql, 'Changing an existing column definition can rewrite data or break compatibility.');
        }

        if (preg_match('/^ADD\s+(?:COLUMN\s+)?(?:CONSTRAINT|PRIMARY|UNIQUE|INDEX|KEY|FOREIGN|CHECK|FULLTEXT|SPATIAL)\b/i', $action) === 1) {
            return $this->finding($statement, Severity::Unanalyzed, $sql, 'Unsupported ALTER TABLE operation.');
        }

        $columnPattern = '/^ADD\s+(?:COLUMN\s+)?'.self::IDENTIFIER.'\s+(?<definition>.+)$/is';
        if (preg_match($columnPattern, $action, $matches) === 1) {
            $definition = $matches['definition'];
            $notNull = $this->scanner->hasTopLevelKeyword($definition, 'NOT')
                && $this->scanner->hasTopLevelKeyword($definition, 'NULL');
            $hasDefault = $this->scanner->hasTopLevelKeyword($definition, 'DEFAULT');

            if ($notNull && !$hasDefault) {
                return $this->finding(
                    $statement,
                    Severity::High,
                    $sql,
                    'Adding a NOT NULL column without a default can fail for existing rows or require an expensive rewrite.',
                );
            }

            if ($notNull) {
                return $this->finding(
                    $statement,
                    Severity::Warning,
                    $sql,
                    'Adding a NOT NULL column with a default can impact an existing table.',
                );
            }

            return $this->finding(
                $statement,
                Severity::Info,
                $sql,
                'Adding a nullable column is a recognized low-risk schema change under the static ruleset.',
            );
        }

        return $this->finding($statement, Severity::Unanalyzed, $sql, 'Unsupported ALTER TABLE operation.');
    }

    private function finding(ExtractedStatement $statement, Severity $severity, string $sql, string $reason): Finding
    {
        return new Finding($severity, $statement->line, $sql, $reason);
    }
}
