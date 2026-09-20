<?php

declare(strict_types=1);

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Egulias\EmailValidator\EmailValidator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

function validationConcerns(): object
{
    return new class
    {
        use PasswordValidationRules {
            passwordRules as public;
        }
        use ProfileValidationRules {
            profileRules as public;
            emailRules as public;
        }
    };
}

describe('production password validation', function (): void {
    beforeEach(function (): void {
        app()->instance('env', 'production');
        Http::preventStrayRequests();
    });

    afterEach(function (): void {
        app()->instance('env', 'testing');
    });

    test('invalid confirmation avoids password breach requests', function (mixed $confirmation): void {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
        $validator = Validator::make([
            'password' => 'Validation-Fixture-7!',
            'password_confirmation' => $confirmation,
        ], ['password' => validationConcerns()->passwordRules()]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->failed()['password'])->toHaveKey('Confirmed');
        Http::assertNothingSent();
    })->with([
        'mismatch' => ['different'],
        'missing' => [null],
        'invalid type' => [['Validation-Fixture-7!']],
    ]);

    test('confirmed passwords retain production complexity requirements without remote work', function (mixed $password): void {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
        $validator = Validator::make([
            'password' => $password,
            'password_confirmation' => $password,
        ], ['password' => validationConcerns()->passwordRules()]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('password'))->toBeTrue();
        Http::assertNothingSent();
    })->with([
        'missing' => [null],
        'invalid type' => [['Validation-Fixture-7!']],
        'too short' => ['Short-7!'],
        'no lowercase' => ['VALIDATION-FIXTURE-7!'],
        'no uppercase' => ['validation-fixture-7!'],
        'no numbers' => ['Validation-Fixture!'],
        'no symbols' => ['ValidationFixture7'],
    ]);

    test('confirmed strong passwords still check breaches before acceptance', function (bool $compromised): void {
        $password = 'Validation-Fixture-7!';
        $suffix = substr(strtoupper(sha1($password)), 5);
        Http::fake(['api.pwnedpasswords.com/*' => Http::response($suffix.':'.($compromised ? '1' : '0'), 200)]);

        $validator = Validator::make([
            'password' => $password,
            'password_confirmation' => $password,
        ], ['password' => validationConcerns()->passwordRules()]);

        expect($validator->passes())->toBe(! $compromised);
        Http::assertSentCount(1);
    })->with([true, false]);
});

test('overlong profile emails skip email parsing and database work', function (): void {
    $parserCalls = 0;
    app()->bind(EmailValidator::class, function () use (&$parserCalls): EmailValidator {
        $parserCalls++;

        return new EmailValidator;
    });
    $validator = Validator::make([
        'email' => str_repeat('a', 256).'@example.test',
    ], ['email' => validationConcerns()->emailRules()]);

    $queries = countDatabaseQueries(function () use ($validator): void {
        expect($validator->fails())->toBeTrue()
            ->and($validator->failed()['email'])->toHaveKey('Max');
    });

    expect($parserCalls)->toBe(0)
        ->and($queries)->toBe(0);
});

test('profile email uniqueness preserves self exclusion and checks other users', function (string $scenario): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $email = match ($scenario) {
        'self' => $user->email,
        'other' => $other->email,
        'new' => 'available@example.test',
    };
    $validator = Validator::make(['email' => $email], [
        'email' => validationConcerns()->emailRules($scenario === 'new' ? null : $user->id),
    ]);

    $queries = countDatabaseQueries(function () use ($validator, $scenario): void {
        expect($validator->passes())->toBe($scenario !== 'other');
    });

    expect($queries)->toBe(1);
})->with(['self', 'other', 'new']);

test('invalid profile fields remain independently validated without database work', function (mixed $email): void {
    $validator = Validator::make([
        'name' => '',
        'email' => $email,
        'locale' => 'de',
    ], validationConcerns()->profileRules(includeLocale: true));

    $queries = countDatabaseQueries(function () use ($validator): void {
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->keys())->toBe(['name', 'email', 'locale']);
    });

    expect($queries)->toBe(0);
})->with([
    'missing' => [null],
    'invalid type' => [['user@example.test']],
    'invalid format' => ['not-an-email'],
]);

test('profile validation accepts each supported locale', function (string $locale): void {
    $validator = Validator::make([
        'name' => 'Validation Fixture',
        'email' => 'available@example.test',
        'locale' => $locale,
    ], validationConcerns()->profileRules(includeLocale: true));

    expect($validator->passes())->toBeTrue();
})->with(['en', 'lt', 'ru']);

test('profile validation keeps locale optional for callers that omit it', function (): void {
    $validator = Validator::make([
        'name' => 'Validation Fixture',
        'email' => 'available@example.test',
    ], validationConcerns()->profileRules());

    expect($validator->passes())->toBeTrue();
});
