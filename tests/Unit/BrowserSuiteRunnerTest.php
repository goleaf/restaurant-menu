<?php

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Support\BrowserSuiteRunner;

test('browser coordinator discovers datasets and rejects empty or duplicate discovery', function () {
    $xml = '<testSuite><tests><testClass><testMethod id="Suite::method#dataset &quot;mobile&quot;"/><testMethod id="Suite::method#dataset &quot;desktop&quot;"/></testClass></tests></testSuite>';
    $ids = BrowserSuiteRunner::discoveredTests($xml);
    expect($ids)->toHaveCount(2)
        ->and(preg_match(BrowserSuiteRunner::filterFor($ids[0], ['Suite::method' => 'Tests\\Suite::a name: punctuation_and spaces']), 'Tests\\Suite::a name: punctuation_and spaces with data set "dataset "mobile""'))->toBe(1)
        ->and(preg_match(BrowserSuiteRunner::filterFor($ids[0], ['Suite::method' => 'Tests\\Suite::a name: punctuation_and spaces']), 'Tests\\Suite::a name: punctuation_and spaces with data set "dataset "desktop""'))->toBe(0);
    expect(fn () => BrowserSuiteRunner::discoveredTests('<testSuite/>'))->toThrow(RuntimeException::class);
});

test('browser coordinator reports child failures and enforces a process timeout', function () {
    $runner = new BrowserSuiteRunner(dirname(__DIR__, 2), sys_get_temp_dir());
    expect($runner->runProcess([PHP_BINARY, '-r', 'fwrite(STDOUT, "Tests: 1 passed\\n"); exit(7);'], [], 5)['exit_code'])->toBe(7);
    $result = $runner->runProcess([PHP_BINARY, '-r', 'sleep(10);'], [], 1);
    expect($result['exit_code'])->toBe(124)->and($result['timeout'])->toBeTrue();
});

test('Playwright shutdown preload leaves unmarked and unrelated node processes unaffected', function (string|false $marker, string $command) {
    $node = (new ExecutableFinder)->find('node') ?? throw new RuntimeException('Node is required for the browser coordinator.');
    $root = dirname(__DIR__, 2);
    $process = new Process([$node, '-e', <<<'JS'
        process.on('SIGTERM', () => console.log('normal-shutdown-handler'));
        setInterval(() => {}, 100);
        setTimeout(() => process.kill(process.pid, 'SIGTERM'), 10);
        setTimeout(() => process.exit(7), 1300);
        JS, '--', $command], $root, [
        'NODE_OPTIONS' => '--require '.json_encode($root.'/tests/Support/browser-playwright-shutdown.cjs', JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'RESTAURANT_BROWSER_COORDINATOR' => $marker,
    ], timeout: 4);

    expect($process->run())->toBe(7)
        ->and($process->getOutput())->toContain('normal-shutdown-handler');
})->with([
    'no coordinator marker' => [false, 'run-server'],
    'disabled coordinator marker' => ['0', 'run-server'],
    'unrelated node command' => ['1', 'another-command'],
]);

test('browser shutdown fallback allows natural node exit during its grace period', function () {
    $node = (new ExecutableFinder)->find('node') ?? throw new RuntimeException('Node is required for the browser coordinator.');
    $runner = new BrowserSuiteRunner(dirname(__DIR__, 2), sys_get_temp_dir());
    $result = $runner->runProcess([$node, '-e', <<<'JS'
        const keepAlive = setInterval(() => {}, 100);
        process.on('SIGTERM', () => {
            console.log('natural-shutdown');
            clearInterval(keepAlive);
        });
        setTimeout(() => process.kill(process.pid, 'SIGTERM'), 10);
        JS, '--', 'run-server'], [], 4);

    expect($result['exit_code'])->toBe(0)
        ->and($result['timeout'])->toBeFalse()
        ->and($result['output'])->toContain('natural-shutdown');
});

test('browser coordinator treats interrupted JUnit as failure and accepts the next complete case', function () {
    expect(BrowserSuiteRunner::caseResult('')['passed'])->toBeFalse()
        ->and(BrowserSuiteRunner::caseResult('<testsuites><testsuite>')['passed'])->toBeFalse()
        ->and(BrowserSuiteRunner::caseResult('<testsuite><testcase assertions="3"/></testsuite>'))->toBe(['passed' => true, 'assertions' => 3])
        ->and(BrowserSuiteRunner::caseResult('<testsuite><testcase><failure>Failed</failure></testcase></testsuite>')['passed'])->toBeFalse();
});

test('browser coordinator bounds owned Playwright shutdown and preserves quoted preload paths and node options', function () {
    $node = (new ExecutableFinder)->find('node') ?? throw new RuntimeException('Node is required for the browser coordinator.');
    $directory = sys_get_temp_dir().'/restaurant-browser-shutdown-'.bin2hex(random_bytes(8));
    $root = $directory.'/root with "quotes" and spaces';
    $existingPreload = $directory.'/existing node options.cjs';
    mkdir($directory, 0700);
    symlink(dirname(__DIR__, 2), $root);
    file_put_contents($existingPreload, 'globalThis.existingPreloadLoaded = true;');
    $previousOptions = getenv('NODE_OPTIONS');
    putenv('NODE_OPTIONS=--require '.json_encode($existingPreload, JSON_THROW_ON_ERROR));

    try {
        $started = microtime(true);
        $result = (new BrowserSuiteRunner($root, $directory))->runProcess([$node, '-e', <<<'JS'
            console.log(globalThis.existingPreloadLoaded ? 'existing-options-preserved' : 'existing-options-lost');
            process.on('SIGTERM', () => console.log('normal-shutdown-handler'));
            setInterval(() => {}, 100);
            setTimeout(() => process.kill(process.pid, 'SIGTERM'), 10);
            JS, '--', 'run-server'], [], 4);

        expect($result['exit_code'])->toBe(143)
            ->and($result['timeout'])->toBeFalse()
            ->and($result['output'])->toContain('existing-options-preserved', 'normal-shutdown-handler')
            ->and(microtime(true) - $started)->toBeLessThan(3.5);
    } finally {
        putenv($previousOptions === false ? 'NODE_OPTIONS' : 'NODE_OPTIONS='.$previousOptions);
        unlink($existingPreload);
        unlink($root);
        rmdir($directory);
    }
});
