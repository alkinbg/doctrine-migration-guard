<?php

declare(strict_types=1);

namespace AlkinBG\DoctrineMigrationGuard\Migration;

use PhpParser\Error;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
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
        if ($code === false) {
            return new MigrationExtraction([], [new ExtractionIssue(null, 'Migration file could not be read.')]);
        }

        try {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            $ast = $parser->parse($code) ?? [];
        } catch (Error $error) {
            $line = $error->getStartLine();

            return new MigrationExtraction([], [
                new ExtractionIssue($line > 0 ? $line : null, 'PHP parse error: '.$error->getMessage()),
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
            if ($class->extends !== null && $class->extends->toString() === 'Doctrine\\Migrations\\AbstractMigration') {
                $migrationClasses[] = $class;
            }
        }

        if ($migrationClasses === []) {
            return new MigrationExtraction([], [
                new ExtractionIssue(null, 'File does not contain a Doctrine AbstractMigration subclass.'),
            ]);
        }

        if (count($migrationClasses) !== 1) {
            return new MigrationExtraction([], [
                new ExtractionIssue(null, 'File contains multiple Doctrine migration classes.'),
            ]);
        }

        $up = $this->findUpMethod($migrationClasses[0]);
        if ($up === null) {
            return new MigrationExtraction([], [
                new ExtractionIssue(null, 'Doctrine migration does not define up().'),
            ]);
        }

        if ($up->stmts === null) {
            return new MigrationExtraction([], [
                new ExtractionIssue($up->getStartLine(), 'Doctrine migration up() has no executable body.'),
            ]);
        }

        /** @var list<ExtractedStatement> $statements */
        $statements = [];
        /** @var list<ExtractionIssue> $issues */
        $issues = [];

        foreach ($up->stmts as $statement) {
            if ($statement instanceof Nop) {
                continue;
            }

            if (!$statement instanceof Expression || !$statement->expr instanceof MethodCall) {
                $issues[] = new ExtractionIssue($this->positiveLine($statement->getStartLine()), 'Unsupported executable statement in up().');
                continue;
            }

            $call = $statement->expr;
            if (!$this->isDirectAddSqlCall($call)) {
                $issues[] = new ExtractionIssue($this->positiveLine($statement->getStartLine()), 'Unsupported executable statement in up().');
                continue;
            }

            $args = $call->getArgs();
            if ($args === []) {
                $issues[] = new ExtractionIssue($this->positiveLine($statement->getStartLine()), 'addSql() requires a SQL argument.');
                continue;
            }

            $sql = $this->resolveStaticString($args[0]->value);
            if ($sql === null) {
                $issues[] = new ExtractionIssue($this->positiveLine($statement->getStartLine()), 'addSql() SQL argument is not statically resolvable.');
                continue;
            }

            $statements[] = new ExtractedStatement($sql, $statement->getStartLine());
        }

        return new MigrationExtraction($statements, $issues);
    }

    private function findUpMethod(Class_ $class): ?ClassMethod
    {
        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) === 'up') {
                return $method;
            }
        }

        return null;
    }

    private function isDirectAddSqlCall(MethodCall $call): bool
    {
        return $call->var instanceof Variable
            && $call->var->name === 'this'
            && $call->name instanceof Identifier
            && strtolower($call->name->toString()) === 'addsql';
    }

    private function resolveStaticString(Expr $expr): ?string
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }

        if ($expr instanceof Concat) {
            $left = $this->resolveStaticString($expr->left);
            $right = $this->resolveStaticString($expr->right);

            return $left !== null && $right !== null ? $left.$right : null;
        }

        return null;
    }

    private function positiveLine(int $line): ?int
    {
        return $line > 0 ? $line : null;
    }
}
