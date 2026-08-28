# Contributing to Doctrine Migration Guard

Thanks for your interest in contributing to Doctrine Migration Guard.

The project aims to stay small, conservative, deterministic, and CI-friendly. Contributions are welcome, but changes should preserve those goals.

## Before You Start

Please open an issue before starting larger changes, especially for:

- new SQL risk rules;
- support for additional migration patterns;
- new CLI options;
- changes to exit-code behavior;
- changes to JSON output;
- support for additional database platforms.

Small bug fixes, tests, documentation improvements, and clearly scoped corrections can be submitted directly.

## Development Setup

Install dependencies:

```bash
composer install
```

Run the complete local checks:

```bash
composer validate --strict
composer test
composer analyse
```

All checks should pass before submitting a pull request.

## Coding Guidelines

Please keep changes focused and easy to review.

In particular:

- use `declare(strict_types=1);`;
- preserve existing namespaces and project structure;
- prefer explicit, small changes over new abstractions;
- avoid adding runtime dependencies unless they are clearly necessary;
- do not introduce database connections, application bootstrapping, or migration execution;
- keep the analyzer fail-closed when behavior cannot be determined reliably;
- avoid guessing when parsing ambiguous PHP or SQL;
- preserve deterministic output and exit-code behavior.

## Tests

Bug fixes should include a regression test whenever practical.

New analysis behavior should include focused tests covering both:

- the supported case;
- the fail-closed or unsupported case where relevant.

Please run:

```bash
composer test
composer analyse
```

before submitting your change.

## Scope

Doctrine Migration Guard v0.x intentionally has a narrow scope.

The project currently focuses on:

- Doctrine migration PHP files;
- supported top-level `addSql()` calls inside `up()`;
- MySQL and MariaDB SQL;
- static risk classification;
- CI usage.

Features such as database metadata inspection, SQL execution, PostgreSQL support, general PHP data-flow analysis, configuration systems, or automatic fixes are currently outside the project scope unless discussed first.

## Pull Requests

Please keep pull requests small and focused.

A good pull request should:

- explain the problem being solved;
- describe the chosen approach;
- include tests for behavior changes;
- avoid unrelated formatting or refactoring;
- keep backward compatibility in mind for CLI behavior, exit codes, and JSON output.

## Reporting Bugs

When reporting a bug, please include:

- the migration code or SQL that triggered the issue;
- the expected result;
- the actual result;
- the Doctrine Migration Guard version;
- the PHP version.

For security-sensitive issues, please follow the project's security policy instead of opening a public issue.

## License

By contributing to this project, you agree that your contributions will be licensed under the MIT License used by the project.
