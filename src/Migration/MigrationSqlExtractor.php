<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Migration;

use PhpParser\Error;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Nop;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final class MigrationSqlExtractor
{
    public function extract(string $path): MigrationExtraction
    {
        if (!is_file($path) || !is_readable($path)) {
            return new MigrationExtraction([], [new ExtractionIssue(null, 'Migration file could not be read.')]);
        }

        $code = file_get_contents($path);
        if (false === $code) {
            return new MigrationExtraction([], [new ExtractionIssue(null, 'Migration file could not be read.')]);
        }

        try {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            $ast = $parser->parse($code) ?? [];
        } catch (Error $error) {
            $line = $error->getStartLine();

            return new MigrationExtraction([], [
                new ExtractionIssue($line > 0 ? $line : null, 'PHP parse error: ' . $error->getMessage()),
            ]);
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $ast = $traverser->traverse($ast);

        $finder = new NodeFinder();
        $classes = $finder->findInstanceOf($ast, Class_::class);

        /** @var list<Class_> $migrationClasses */
        $migrationClasses = [];
        foreach ($classes as $class) {
            if (null !== $class->extends && 'Doctrine\\Migrations\\AbstractMigration' === $class->extends->toString()) {
                $migrationClasses[] = $class;
            }
        }

        if ($migrationClasses === []) {
            return new MigrationExtraction([], [
                new ExtractionIssue(null, 'File does not contain a Doctrine AbstractMigration subclass.'),
            ]);
        }

        if (1 !== count($migrationClasses)) {
            return new MigrationExtraction([], [
                new ExtractionIssue(null, 'File contains multiple Doctrine migration classes.'),
            ]);
        }

        $migrationClass = $migrationClasses[0];

        $up = $this->findUpMethod($migrationClass);
        if (null === $up) {
            return new MigrationExtraction([], [
                new ExtractionIssue(null, 'Doctrine migration does not define up().'),
            ]);
        }

        if (null === $up->stmts) {
            return new MigrationExtraction([], [
                new ExtractionIssue($up->getStartLine(), 'Doctrine migration up() has no executable body.'),
            ]);
        }

        /** @var list<ExtractedStatement> $statements */
        $statements = [];
        /** @var list<ExtractionIssue> $issues */
        $issues = [];
        $preUp = $this->findMethod($migrationClass, 'preUp');

        if (null !== $preUp) {
            $issues[] = new ExtractionIssue(
                $this->positiveLine($preUp->getStartLine()),
                'Doctrine migration overrides preUp(), which is not analyzed.',
            );
        }

        $postUp = $this->findMethod($migrationClass, 'postUp');

        if (null !== $postUp) {
            $issues[] = new ExtractionIssue(
                $this->positiveLine($postUp->getStartLine()),
                'Doctrine migration overrides postUp(), which is not analyzed.',
            );
        }

        foreach ($up->stmts as $statement) {
            if ($statement instanceof Nop) {
                continue;
            }

            if (!$statement instanceof Expression || !$statement->expr instanceof MethodCall) {
                $issues[] = new ExtractionIssue(
                    $this->positiveLine($statement->getStartLine()),
                    'Unsupported executable statement in up().'
                );
                continue;
            }

            $call = $statement->expr;
            if (!$this->isDirectAddSqlCall($call)) {
                $issues[] = new ExtractionIssue(
                    $this->positiveLine($statement->getStartLine()),
                    'Unsupported executable statement in up().'
                );
                continue;
            }

            $args = $call->getArgs();
            if ($args === []) {
                $issues[] = new ExtractionIssue(
                    $this->positiveLine($statement->getStartLine()),
                    'addSql() requires a SQL argument.'
                );
                continue;
            }

            $sql = $this->resolveStaticString($args[0]->value);
            if (null === $sql) {
                $issues[] = new ExtractionIssue(
                    $this->positiveLine($statement->getStartLine()),
                    'addSql() SQL argument is not statically resolvable.'
                );
                continue;
            }

            foreach (array_slice($args, 1) as $argument) {
                if (!$this->isStaticDataExpression($argument->value)) {
                    $issues[] = new ExtractionIssue(
                        $this->positiveLine($statement->getStartLine()),
                        'addSql() contains an unsupported additional argument.',
                    );

                    break;
                }
            }

            $statements[] = new ExtractedStatement($sql, $statement->getStartLine());
        }

        return new MigrationExtraction($statements, $issues);
    }

    private function findUpMethod(Class_ $class): ?ClassMethod
    {
        return $this->findMethod($class, 'up');
    }

    private function isDirectAddSqlCall(MethodCall $call): bool
    {
        return $call->var instanceof Variable
            && 'this' === $call->var->name
            && $call->name instanceof Identifier
            && 'addsql' === strtolower($call->name->toString());
    }

    private function resolveStaticString(Expr $expr): ?string
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }

        if ($expr instanceof Concat) {
            $left = $this->resolveStaticString($expr->left);
            $right = $this->resolveStaticString($expr->right);

            return null !== $left && null !== $right ? $left . $right : null;
        }

        return null;
    }

    private function positiveLine(int $line): ?int
    {
        return $line > 0 ? $line : null;
    }

    private function isStaticDataExpression(Expr $expr): bool
    {
        if (
            $expr instanceof String_
            || $expr instanceof Int_
            || $expr instanceof Float_
        ) {
            return true;
        }

        if ($expr instanceof ConstFetch) {
            return in_array(
                strtolower($expr->name->toString()),
                ['true', 'false', 'null'],
                true,
            );
        }

        if (
            $expr instanceof ClassConstFetch
            && $expr->class instanceof Name
            && $expr->name instanceof Identifier
        ) {
            return true;
        }

        if ($expr instanceof UnaryMinus || $expr instanceof UnaryPlus) {
            return $expr->expr instanceof Int_ || $expr->expr instanceof Float_;
        }

        if ($expr instanceof Array_) {
            foreach ($expr->items as $item) {
                if ($item->byRef || $item->unpack) {
                    return false;
                }

                if (
                    null !== $item->key
                    && !$this->isStaticDataExpression($item->key)
                ) {
                    return false;
                }

                if (!$this->isStaticDataExpression($item->value)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function findMethod(Class_ $class, string $name): ?ClassMethod
    {
        foreach ($class->getMethods() as $method) {
            if (0 === strcasecmp($method->name->toString(), $name)) {
                return $method;
            }
        }

        return null;
    }
}
