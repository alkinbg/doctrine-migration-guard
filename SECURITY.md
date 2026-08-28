# Security Policy

## Supported Versions

Doctrine Migration Guard is currently in the `0.x` development series.

Security fixes are provided for the latest released `0.x` version.

| Version | Supported |
| --- | --- |
| `0.1.x` | Yes |
| Older versions | No |

## Reporting a Vulnerability

Please do not open a public GitHub issue for security-sensitive reports.

Report potential vulnerabilities privately to:

**Alkin Fehim**
alkinbg@gmail.com

Please include, where possible:

- the affected Doctrine Migration Guard version;
- a clear description of the issue;
- steps to reproduce it;
- a minimal migration or SQL example that demonstrates the problem;
- the potential security impact;
- any suggested mitigation or fix, if known.

Reports should contain enough detail to reproduce and evaluate the issue without requiring access to private systems or production data.

## Scope

Security reports are especially relevant when they involve:

- unintended execution of migration code;
- unexpected database or network access;
- command execution;
- unsafe handling of untrusted migration source files;
- path traversal or unintended file access;
- parser behavior that could bypass fail-closed analysis in a security-relevant way;
- dependency vulnerabilities that directly affect Doctrine Migration Guard.

Incorrect risk classification that does not create a security vulnerability may be reported as a regular GitHub issue instead.

## Disclosure

Please allow reasonable time to investigate and, where necessary, prepare a fix before publicly disclosing a vulnerability.
Security fixes may be released without publishing exploit details until users have had a reasonable opportunity to update.

## Security Model

Doctrine Migration Guard is designed as a static analyzer.

It should not:

- execute Doctrine migrations;
- boot a Symfony application;
- connect to a database;
- make network requests;
- evaluate migration PHP code;
- execute SQL.

Unsupported or ambiguous constructs are intended to fail closed rather than being treated as successfully analyzed.

A `PASSED` result is a static analysis result and is not a guarantee that a migration is operationally or security-safe in every environment.
