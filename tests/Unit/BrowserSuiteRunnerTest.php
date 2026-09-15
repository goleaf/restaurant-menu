<?php

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
    expect($runner->runProcess([PHP_BINARY, '-r', 'exit(7);'], [], 5)['exit_code'])->toBe(7);
    $result = $runner->runProcess([PHP_BINARY, '-r', 'sleep(10);'], [], 1);
    expect($result['exit_code'])->toBe(124)->and($result['timeout'])->toBeTrue();
});

test('browser coordinator treats interrupted JUnit as failure and accepts the next complete case', function () {
    expect(BrowserSuiteRunner::caseResult('')['passed'])->toBeFalse()
        ->and(BrowserSuiteRunner::caseResult('<testsuites><testsuite>')['passed'])->toBeFalse()
        ->and(BrowserSuiteRunner::caseResult('<testsuite><testcase assertions="3"/></testsuite>'))->toBe(['passed' => true, 'assertions' => 3])
        ->and(BrowserSuiteRunner::caseResult('<testsuite><testcase><failure>Failed</failure></testcase></testsuite>')['passed'])->toBeFalse();
});
