# Pull Request

## What does this change?

Briefly describe the problem and the change made to solve it.

## Why is this needed?

Explain the concrete migration-analysis or CI problem this addresses.

## Type of change

- [ ] Bug fix
- [ ] New analysis rule
- [ ] Migration extraction change
- [ ] CLI behavior change
- [ ] JSON output change
- [ ] Documentation
- [ ] Tests
- [ ] Other

## Testing

Describe the tests you added or changed.

Please confirm that the full local checks pass:

```bash
composer validate --strict
composer test
composer analyse
```

- [ ] `composer validate --strict` passes
- [ ] `composer test` passes
- [ ] `composer analyse` passes
- [ ] `git diff --check` is clean

## Compatibility and scope

- [ ] The change is focused and does not include unrelated formatting or refactoring.
- [ ] CLI behavior, exit codes, and JSON output remain backward compatible unless the change explicitly requires otherwise.
- [ ] Unsupported or ambiguous behavior still fails closed.
- [ ] No database connection, migration execution, application bootstrapping, or network access was introduced.
- [ ] New runtime dependencies were avoided unless clearly necessary.

## Notes for reviewers

Add any edge cases, limitations, or follow-up work that reviewers should know about.
