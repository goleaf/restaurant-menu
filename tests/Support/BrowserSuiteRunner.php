<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class BrowserSuiteRunner
{
    public function __construct(private readonly string $root, private readonly string $artifacts) {}

    /** @return list<string> */
    public static function discoveredTests(string $xml): array
    {
        $document = simplexml_load_string($xml, options: LIBXML_NONET);
        if ($document === false) {
            throw new RuntimeException('Invalid browser test discovery document.');
        }
        $ids = [];
        foreach ($document->xpath('//*[local-name()="testMethod"]') ?: [] as $test) {
            $ids[] = (string) $test['id'];
        }
        if ($ids === [] || count(array_unique($ids)) !== count($ids)) {
            throw new RuntimeException('Browser discovery must contain unique test cases, including datasets.');
        }

        return $ids;
    }

    /** @param array<string,string> $names */
    public static function filterFor(string $id, array $names = []): string
    {
        [$method, $dataset] = array_pad(explode('#', $id, 2), 2, null);
        $name = $names[$method] ?? $method;
        if ($dataset !== null) {
            $name .= ctype_digit($dataset) ? ' with data set #'.$dataset : ' with data set "'.$dataset.'"';
        }

        return '/^'.preg_quote((string) $name, '/').'$/D';
    }

    /** @param list<string> $command @param array<string,string> $environment @return array{exit_code:int,timeout:bool,output:string} */
    public function runProcess(array $command, array $environment, int $timeout): array
    {
        $preload = json_encode($this->root.'/tests/Support/browser-playwright-shutdown.cjs', JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $environment['NODE_OPTIONS'] = trim(($environment['NODE_OPTIONS'] ?? (getenv('NODE_OPTIONS') ?: '')).' --require '.$preload);
        $environment['RESTAURANT_BROWSER_COORDINATOR'] = '1';
        $process = new Process([PHP_BINARY, $this->root.'/tests/browser-child.php', ...$command], $this->root, $environment, timeout: $timeout);
        $process->start();
        $pid = $process->getPid();
        $timedOut = false;
        pcntl_async_signals(true);
        $handlers = [];
        foreach ([SIGTERM, SIGINT, SIGHUP] as $signal) {
            $handlers[$signal] = pcntl_signal_get_handler($signal);
            pcntl_signal($signal, function (int $received) use ($pid): void {
                if (is_int($pid)) {
                    posix_kill(-$pid, SIGKILL);
                }
                throw new RuntimeException('Browser suite interrupted by signal '.$received.'.');
            });
        }
        try {
            $process->wait();
        } catch (ProcessTimedOutException) {
            $timedOut = true;
        } finally {
            foreach ($handlers as $signal => $handler) {
                pcntl_signal($signal, $handler);
            }
            if (is_int($pid)) {
                posix_kill(-$pid, SIGTERM);
                // The isolated session contains only this runner's child and its descendants.
                posix_kill(-$pid, SIGKILL);
            }
        }

        return ['exit_code' => $timedOut ? 124 : ($process->getExitCode() ?? 1), 'timeout' => $timedOut, 'output' => $process->getOutput().$process->getErrorOutput()];
    }

    /** @return array{passed:bool,assertions:int} */
    public static function caseResult(string $xml): array
    {
        if (trim($xml) === '') {
            return ['passed' => false, 'assertions' => 0];
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = simplexml_load_string($xml, options: LIBXML_NONET);
            $cases = $document === false ? [] : ($document->xpath('//testcase') ?: []);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (count($cases) !== 1) {
            return ['passed' => false, 'assertions' => 0];
        }

        return ['passed' => ! isset($cases[0]->failure) && ! isset($cases[0]->error) && ! isset($cases[0]->skipped), 'assertions' => (int) $cases[0]['assertions']];
    }

    public function run(string $browser, int $timeout): int
    {
        $environment = $this->environment('discovery');
        $discoveryPath = $this->artifacts.'/discovery.xml';
        $namesPath = $this->artifacts.'/test-names.json';
        $environment['RESTAURANT_BROWSER_NAMES'] = $namesPath;
        $discovery = $this->runProcess([PHP_BINARY, 'tests/browser-discover.php', 'tests/Browser', '--browser', $browser, '--list-tests-xml', $discoveryPath], $environment, 60);
        if ($discovery['exit_code'] !== 0 || ! is_file($discoveryPath) || ! is_file($namesPath)) {
            fwrite(STDERR, "Browser discovery failed.\n".$discovery['output']);

            return 1;
        }
        $tests = self::discoveredTests((string) file_get_contents($discoveryPath));
        $names = json_decode((string) file_get_contents($namesPath), true, flags: JSON_THROW_ON_ERROR);
        $results = [];
        $assertions = 0;
        foreach ($tests as $index => $id) {
            $number = $index + 1;
            $case = 'case-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $junit = $this->artifacts.'/'.$case.'.xml';
            fwrite(STDOUT, '['.$number.'/'.count($tests).'] '.$id."\n");
            $result = $this->runProcess([PHP_BINARY, 'vendor/bin/pest', 'tests/Browser', '--browser', $browser, '--compact', '--filter', self::filterFor($id, $names), '--log-junit', $junit], $this->environment($case), $timeout);
            file_put_contents($this->artifacts.'/'.$case.'.log', $result['output']);
            $caseResult = self::caseResult(is_file($junit) ? (string) file_get_contents($junit) : '');
            $assertions += $caseResult['assertions'];
            $valid = $caseResult['passed'];
            $passed = $result['exit_code'] === 0 && $valid;
            $results[] = ['id' => $id, 'passed' => $passed, 'exit_code' => $result['exit_code'], 'timeout' => $result['timeout']];
            fwrite(STDOUT, ($passed ? 'PASS' : ($result['timeout'] ? 'TIMEOUT' : 'FAIL'))."\n");
            if (! $passed) {
                fwrite(STDOUT, $result['output']);
            }
            foreach (glob($this->root.'/tests/Browser/Screenshots/*') ?: [] as $screenshot) {
                if (is_file($screenshot)) {
                    copy($screenshot, $this->artifacts.'/'.$case.'-'.basename($screenshot));
                }
            }
        }
        $failed = count(array_filter($results, fn (array $result): bool => ! $result['passed']));
        file_put_contents($this->artifacts.'/summary.json', json_encode(['browser' => $browser, 'tests' => count($tests), 'assertions' => $assertions, 'failed' => $failed, 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        fwrite(STDOUT, sprintf("Browser suite: %d cases, %d assertions, %d failures/timeouts. Artifacts: %s\n", count($tests), $assertions, $failed, $this->artifacts));

        return $failed === 0 ? 0 : 1;
    }

    /** @return array<string,string> */
    private function environment(string $case): array
    {
        $runtime = $this->artifacts.'/'.$case;
        foreach (['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'cache'] as $directory) {
            if (! is_dir($runtime.'/'.$directory)) {
                mkdir($runtime.'/'.$directory, 0700, true);
            }
        }
        copy($this->root.'/storage/app/public/.htaccess', $runtime.'/app/public/.htaccess');

        return ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            'LARAVEL_STORAGE_PATH' => $runtime, 'VIEW_COMPILED_PATH' => $runtime.'/framework/views',
            'APP_CONFIG_CACHE' => $runtime.'/cache/config.php', 'APP_ROUTES_CACHE' => $runtime.'/cache/routes.php', 'APP_EVENTS_CACHE' => $runtime.'/cache/events.php',
            'PAO_DISABLE' => '1', 'COMPOSER_PROCESS_TIMEOUT' => '0'];
    }
}
