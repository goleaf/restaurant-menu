<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

test('the project diagnostic policy observes real child PHPUnit issues even when PHP suppresses them', function (string $body, ?string $diagnostic) {
    $root = dirname(__DIR__, 2);
    $directory = sys_get_temp_dir().'/restaurant-diagnostic-policy-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $fixture = $directory.'/VerificationDiagnosticChildTest.php';
    $junit = $directory.'/result.xml';
    file_put_contents($fixture, str_replace('__BODY__', $body, <<<'PHP'
        <?php

        final class VerificationDiagnosticChildTest extends \PHPUnit\Framework\TestCase
        {
            public function testDiagnostic(): void
            {
                __BODY__
                self::assertTrue(true);
            }
        }
        PHP));

    try {
        $process = new Process([
            PHP_BINARY, '-d', 'error_reporting=-1', $root.'/vendor/bin/phpunit',
            '--configuration', $root.'/phpunit.xml', '--no-coverage', '--do-not-cache-result',
            '--cache-directory', $directory.'/cache', '--fail-on-all-issues', '--display-all-issues',
            '--colors=never', '--log-junit', $junit, $fixture,
        ], $directory, [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'MAIL_MAILER' => 'array', 'PAO_DISABLE' => '1', 'XDEBUG_MODE' => 'off',
        ], timeout: 30);
        $exitCode = $process->run();
        $output = $process->getOutput().$process->getErrorOutput();

        expect(is_file($junit))->toBeTrue();
        $document = simplexml_load_file($junit);
        expect($document)->not->toBeFalse();
        $cases = $document->xpath('//testcase');
        expect($cases)->toHaveCount(1)
            ->and((string) $cases[0]['name'])->toBe('testDiagnostic')
            ->and((int) $cases[0]['assertions'])->toBe(1);

        if ($diagnostic === null) {
            $this->assertSame(0, $exitCode, $output);
        } else {
            $this->assertNotSame(0, $exitCode, $output);
            expect($output)->toContain($diagnostic);
        }
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
})->with([
    'clean execution' => ['', null],
    'suppressed user warning' => ['@trigger_error("verification-user-warning", E_USER_WARNING);', 'verification-user-warning'],
    'suppressed user deprecation' => ['@trigger_error("verification-user-deprecation", E_USER_DEPRECATED);', 'verification-user-deprecation'],
    'suppressed native warning' => ['@file_get_contents(__DIR__."/missing-diagnostic-fixture");', 'missing-diagnostic-fixture'],
]);
