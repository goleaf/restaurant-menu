<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

pest()->browser()->timeout(10000);

beforeEach(function (): void {
    app()->setLocale((string) config('app.locale', 'en'));
});

function countDatabaseQueries(Closure $operation): int
{
    $connection = DB::connection();

    $connection->flushQueryLog();
    $connection->enableQueryLog();

    try {
        $operation();

        return count($connection->getQueryLog());
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }
}
