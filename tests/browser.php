<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Tests\Support\BrowserSuiteRunner;

$options = getopt('', ['browser:', 'timeout:']);
$browser = $options['browser'] ?? 'safari';
$timeout = filter_var($options['timeout'] ?? 180, FILTER_VALIDATE_INT, ['options' => ['min_range' => 10, 'max_range' => 900]]);
if (! in_array($browser, ['safari', 'chrome', 'firefox'], true) || $timeout === false || ! function_exists('posix_setsid') || ! function_exists('pcntl_exec')) {
    fwrite(STDERR, "Usage: composer test:browser -- --browser safari|chrome|firefox --timeout 180; requires POSIX and pcntl.\n");
    exit(1);
}
$artifacts = sys_get_temp_dir().'/restaurant-browser-'.bin2hex(random_bytes(8));
mkdir($artifacts, 0700, true);
try {
    exit((new BrowserSuiteRunner(dirname(__DIR__), $artifacts))->run($browser, $timeout));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\nArtifacts: ".$artifacts."\n");
    exit(1);
}
