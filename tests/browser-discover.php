<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Pest\Contracts\HasPrintableTestCaseName;
use Pest\Support\Str;
use Pest\TestSuite;

register_shutdown_function(function (): void {
    $path = getenv('RESTAURANT_BROWSER_NAMES');
    if (! is_string($path) || $path === '') {
        return;
    }
    $names = [];
    foreach (get_declared_classes() as $class) {
        if (! is_subclass_of($class, HasPrintableTestCaseName::class) || ! property_exists($class, '__filename')) {
            continue;
        }
        $factory = TestSuite::getInstance()->tests->get($class::$__filename);
        foreach ($factory?->methods ?? [] as $method) {
            $names[$class.'::'.Str::evaluable($method->description)] = $class::getPrintableTestCaseName().'::'.$method->description;
        }
    }
    file_put_contents($path, json_encode($names, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
});

require dirname(__DIR__).'/vendor/bin/pest';
