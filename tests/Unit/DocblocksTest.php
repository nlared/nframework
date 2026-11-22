<?php

namespace Tests\Unit;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * El núcleo no usa namespaces ni tipos en todas partes y depende de variables globales: los docblocks son
 * lo que permite a un IDE, a un analizador estático o a una IA saber qué recibe y devuelve cada función.
 * Reglas para includes/functions.php, includes/include.php e includes/class.*.php:
 *  - cada clase tiene docblock;
 *  - cada `global $x` de una función o método está documentado con `@global Tipo $x`;
 *  - cada parámetro sin tipo nativo tiene `@param`.
 */
class DocblocksTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function files(): array
    {
        $dir = dirname(__DIR__, 2) . '/includes';
        $files = array_merge([$dir . '/functions.php', $dir . '/include.php'], glob($dir . '/class.*.php') ?: []);
        $cases = [];
        foreach ($files as $file) {
            $cases[basename($file)] = [$file];
        }
        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('files')]
    public function testDocblocksAreComplete(string $file): void
    {
        $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse(file_get_contents($file));
        $finder = new NodeFinder();
        $missing = [];

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name !== null && !$class->getDocComment()) {
                $missing[] = "class {$class->name}: sin docblock";
            }
        }

        $functions = $finder->find($ast, fn($n) => $n instanceof Node\Stmt\Function_ || $n instanceof Node\Stmt\ClassMethod);
        foreach ($functions as $function) {
            $doc = $function->getDocComment()?->getText() ?? '';
            $name = $function->name->name . '() l.' . $function->getStartLine();
            foreach ($function->params as $param) {
                $var = $param->var instanceof Node\Expr\Variable && is_string($param->var->name) ? $param->var->name : null;
                if ($var !== null && $param->type === null && !preg_match('/@param\s[^\n]*\$' . preg_quote($var, '/') . '\b/', $doc)) {
                    $missing[] = "$name: falta @param \$$var";
                }
            }
            foreach ($finder->findInstanceOf($function->stmts ?? [], Node\Stmt\Global_::class) as $global) {
                foreach ($global->vars as $var) {
                    if ($var instanceof Node\Expr\Variable && is_string($var->name)
                        && !preg_match('/@global\s[^\n]*\$' . preg_quote($var->name, '/') . '\b/', $doc)) {
                        $missing[] = "$name: falta @global \${$var->name}";
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), basename($file) . ': docblocks incompletos');
    }
}
