<?php

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

function boundaryAst(string $file): array
{
    $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse(file_get_contents($file));
    $traverser = new NodeTraverser(new NameResolver);

    return $traverser->traverse($nodes ?? []);
}

function boundaryFiles(string $directory): array
{
    return iterator_to_array(new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/'.$directory, FilesystemIterator::SKIP_DOTS)), '/\.php$/'));
}

test('controllers delegate payload validation and presentation queries', function (): void {
    $finder = new NodeFinder;
    $violations = [];
    foreach (boundaryFiles('app/Http/Controllers') as $file) {
        foreach ($finder->findInstanceOf(boundaryAst((string) $file), Node\Expr::class) as $node) {
            $method = $node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\StaticCall ? (string) $node->name : '';
            if (in_array($method, ['validate', 'validateWithBag', 'loadMissing', 'query', 'findAcceptableById'], true)
                || ($node instanceof Node\Expr\StaticCall && (string) $node->class === 'Illuminate\\Support\\Facades\\Validator' && $method === 'make')) {
                $violations[] = basename((string) $file).':'.$node->getStartLine().' '.$method;
            }
        }
    }
    expect($violations)->toBe([]);
});

test('domain actions do not resolve actor or ui state through http session', function (): void {
    $finder = new NodeFinder;
    $violations = [];
    foreach (boundaryFiles('app/Actions') as $file) {
        $nodes = boundaryAst((string) $file);
        foreach ($finder->findInstanceOf($nodes, Node\Expr::class) as $node) {
            if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name && in_array((string) $node->name, ['request', 'auth', 'session', 'redirect'], true)
                || $node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name && in_array((string) $node->class, ['Flux\\Flux', 'Illuminate\\Support\\Facades\\Auth', 'Illuminate\\Support\\Facades\\Session'], true)) {
                $violations[] = basename((string) $file).':'.$node->getStartLine();
            }
        }
        foreach ($finder->findInstanceOf($nodes, Node\Param::class) as $param) {
            if ($param->type instanceof Node\Name && (string) $param->type === 'Illuminate\\Http\\Request') {
                $violations[] = basename((string) $file).':http-request';
            }
        }
    }
    expect($violations)->toBe([]);
});

test('rules and form requests do not mutate persistence or generate ui side effects', function (): void {
    $finder = new NodeFinder;
    $violations = [];
    foreach (['app/Http/Requests', 'app/Rules', 'app/Support/Validation'] as $directory) {
        if (! is_dir(dirname(__DIR__, 2).'/'.$directory)) { continue; }
        foreach (boundaryFiles($directory) as $file) {
            foreach ($finder->findInstanceOf(boundaryAst((string) $file), Node\Expr::class) as $node) {
                if ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier && in_array((string) $node->name, ['save','saveOrFail','delete','deleteOrFail','updateOrCreate','firstOrCreate','sync','attach','detach','notify','redirect','toast'], true)
                    || $node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name && in_array((string) $node->class,['Flux\\Flux','Illuminate\\Support\\Facades\\DB'],true)) {
                    $violations[] = basename((string) $file).':'.$node->getStartLine();
                }
            }
        }
    }
    expect($violations)->toBe([]);
});
