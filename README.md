# Doctrine Migration Guard

**Catch risky Doctrine migrations before they reach production.**

Doctrine Migration Guard is a standalone CLI tool that statically inspects Doctrine migration files and classifies common MySQL/MariaDB schema and data changes by risk.

It does not boot Symfony, execute migrations, connect to a database, or require Doctrine Migrations at runtime.

## What it does

The guard parses migration PHP source with `nikic/php-parser`, inspects `up()`, extracts supported top-level `$this->addSql()` calls, and classifies the SQL using a conservative static ruleset.

The v0.1 ruleset recognizes common operations such as:

- table creation;
- nullable and `NOT NULL` column additions;
- column/table renames and column definition changes;
- `DROP TABLE`, `DROP COLUMN`, and `TRUNCATE`;
- `UPDATE` / `DELETE` statements with and without a top-level `WHERE`;
- normal and unique indexes;
- foreign-key additions and removals.

Unknown or ambiguous PHP/SQL never becomes a green result. Unsupported constructs are reported as `UNANALYZED`, which makes the run `INCOMPLETE`.

## What it does not guarantee

A `PASSED` result does not guarantee that a migration is operationally safe for a particular database, table size, server version, or workload. It means the analyzed code contained no blocking findings and no unsupported constructs under the v0.1 static ruleset.

The tool does not inspect database metadata, table size, server configuration, execution plans, lock duration, MySQL/MariaDB version-specific algorithms, or live migration state.

## Requirements

- PHP `>=8.1`
- Doctrine-style migration PHP files
- SQL intended for MySQL or MariaDB

Runtime dependencies are intentionally small: `nikic/php-parser` and `symfony/console`.

## Installation

```bash
composer require --dev alkinbg/doctrine-migration-guard
```

## Usage

Analyze a migration directory recursively:

```bash
vendor/bin/doctrine-migration-guard migrations/
```

Analyze one file:

```bash
vendor/bin/doctrine-migration-guard migrations/Version20260827120000.php
```

Analyze multiple files:

```bash
vendor/bin/doctrine-migration-guard migrations/A.php migrations/B.php
```

JSON output:

```bash
vendor/bin/doctrine-migration-guard --format=json migrations/
```

Directories are scanned recursively for `*.php` files. Input files are deduplicated and processed in deterministic lexical order.

## Exit codes

| Exit code | Result | Meaning |
| ---: | --- | --- |
| `0` | `PASSED` | Analysis completed with only `INFO` / `WARNING` findings. |
| `1` | `FAILED` | At least one `HIGH` or `CRITICAL` finding was found. |
| `2` | `INCOMPLETE` | At least one input, migration, or SQL statement could not be analyzed reliably. |

Result precedence is `INCOMPLETE > FAILED > PASSED`.

## Risk levels

| Level | Typical meaning |
| --- | --- |
| `INFO` | Recognized low-risk static pattern, such as creating a table or adding a nullable column. |
| `WARNING` | Change deserves review, such as an `UPDATE` / `DELETE` with a top-level `WHERE`, normal index, or foreign key operation. |
| `HIGH` | Significant migration risk, such as an `UPDATE` without a top-level `WHERE`, unique index, rename, or `NOT NULL` column without a default. |
| `CRITICAL` | Destructive operation such as `DROP TABLE`, `DROP COLUMN`, `TRUNCATE`, or a `DELETE` without a top-level `WHERE`. |
| `UNANALYZED` | The guard cannot classify the construct reliably and fails closed. |

Risk levels are static review signals, not database-runtime guarantees.

## Supported v0.1 migration shape

The intentionally narrow happy path is a Doctrine migration with direct top-level `addSql()` calls inside `up()`:

```php
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827120000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD nickname VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_users_email ON users (email)');
    }
}
```

Static string literals, heredoc/nowdoc strings, and concatenations made entirely from static strings can be analyzed.
Additional `addSql()` parameter and type arguments are accepted only when they are statically representable. Dynamic or executable expressions in additional arguments make the migration analysis `INCOMPLETE`.

The following intentionally make analysis incomplete in v0.1:

```php
$sql = 'ALTER TABLE users ADD nickname VARCHAR(64)';
$this->addSql($sql);

if ($condition) {
    $this->addSql('DROP TABLE legacy');
}

$this->connection->executeStatement('UPDATE users SET active = 0');

$schema->getTable('users');
```

Only direct supported operations inside `up()` are analyzed. `down()` is ignored.

Overrides of Doctrine lifecycle hooks such as `preUp()` or `postUp()` are not analyzed in v0.1 and therefore make the migration result `INCOMPLETE`. Supported SQL inside `up()` is still extracted and reported.

## Fail-closed SQL behavior

Doctrine Migration Guard does not guess when SQL is ambiguous.

For example, a multi-action statement such as:

```sql
ALTER TABLE app_users
    ADD last_login_at DATETIME DEFAULT NULL,
    ADD last_seen_at DATETIME DEFAULT NULL
```

is `UNANALYZED` in v0.1 rather than being classified from only the first `ALTER` action.

Likewise, multiple top-level SQL statements inside one `addSql()` call are `UNANALYZED`.

MySQL/MariaDB executable comments and backslash escapes inside quoted SQL are also `UNANALYZED` in v0.1 because their meaning can depend on server dialect or `sql_mode`, which the standalone static analyzer does not inspect.

## JSON output

`--format=json` emits machine-readable schema version `1` and no decorative console text:

```json
{
  "schema_version": 1,
  "result": "failed",
  "files": [
    {
      "path": "migrations/Version20260827120000.php",
      "result": "failed",
      "findings": [
        {
          "severity": "high",
          "line": 18,
          "sql": "UPDATE users SET active = 0",
          "reason": "An UPDATE without a top-level WHERE can modify every row in the target table."
        }
      ]
    }
  ],
  "summary": {
    "info": 0,
    "warning": 0,
    "high": 1,
    "critical": 0,
    "unanalyzed": 0
  }
}
```

The JSON schema is versioned independently from internal PHP classes. PHP implementation classes are not a stable public library API in v0.1.

## CI example

The guard deliberately does not contain built-in Git or pending-migration discovery. Compose file selection externally in CI:

```bash
git diff --name-only origin/main...HEAD -- 'migrations/*.php' \
  | xargs -r vendor/bin/doctrine-migration-guard
```

Or scan the entire migration directory:

```bash
vendor/bin/doctrine-migration-guard migrations/
```

## Limitations

v0.1 intentionally does not include:

- a configuration file or rule suppression system;
- custom rule plugins;
- Git-aware `--changed` / `--since` behavior;
- Doctrine pending-migration discovery;
- database connections or metadata inspection;
- database-version-specific safety guarantees;
- SQL rewriting or automatic fixes;
- PostgreSQL rules;
- general PHP data-flow analysis.

These boundaries keep the first release deterministic, CI-friendly, and conservative.

## Development

Install dependencies and run the complete quality gates:

```bash
composer install
composer validate --strict
composer test
composer analyse
```

The CI matrix tests PHP 8.1, 8.2, 8.3, 8.4, and 8.5.

## License

MIT. See [LICENSE](LICENSE).
