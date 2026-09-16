<?php

use App\Enums\SupportedLocale;
use App\Models\User;
use App\Support\Localization\ValidationMessageCatalogue;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\PresenceVerifierInterface;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

uses(TestCase::class);

test('every installed validator message is exercised in every locale', function (string $key, Closure $fixture): void {
    $catalogue = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
    $template = Arr::dot($catalogue)[$key];
    $this->mock(UncompromisedVerifier::class)->shouldReceive('verify')->andReturnFalse();
    Gate::define('catalogue-denied', fn () => false);
    Auth::guard()->setUser(User::factory()->make(['password' => Hash::make('correct-password')]));
    $presence = Mockery::mock(PresenceVerifierInterface::class);
    $presence->shouldReceive('getCount')->andReturn($key === 'unique' ? 1 : 0);
    $presence->shouldReceive('getMultiCount')->andReturn(0);

    foreach (['en', 'lt', 'ru'] as $locale) {
        app()->setLocale($locale);
        [$data, $rules, $field] = $fixture();
        $validator = Validator::make($data, $rules, [], ['dates.*' => __('validation.attributes.date_from')]);
        $validator->setPresenceVerifier($presence);
        expect($validator->fails())->toBeTrue($key.' must actually fail');
        $message = $validator->errors()->first($field);
        $translated = __('validation.'.$key);
        expect($message)->not->toBe('')->not->toStartWith('validation.')
            ->and($translated)->not->toBe('validation.'.$key)
            ->and(preg_match('/:[a-z][a-z_]+/', $message))->toBe(0, $message);
        if ($locale !== 'en') {
            expect($translated)->not->toBe($template)->and($message)->not->toContain('The ', ' field ');
        }
        if (str_contains($template, ':attribute')) {
            expect($message)->toContain(__('validation.attributes.date_from'));
        }
        // Compare the actual final text to the localized template with Laravel's replacements.
        expect($message)->toBe($validator->makeReplacements($translated, $field, Str::studly(explode('.', $key)[0]), $validator->getRules()[$field] === [] ? [] : catalogueParameters($key, $rules[$field] ?? [])));
    }
})->with(fn () => validationCatalogueCases());

test('runtime catalogue and executable dataset match the installed validator exactly', function (): void {
    $messages = ValidationMessageCatalogue::keys();
    $cases = array_map(fn (array $case): string => 'validation.'.$case[0], validationCatalogueCases());
    sort($messages);
    sort($cases);
    expect($cases)->toBe($messages);
});

test('flat json validation attributes are loaded through the normal translator groups', function (): void {
    foreach (['lt', 'ru', 'en', 'lt'] as $locale) {
        app()->setLocale($locale);
        $validator = Validator::make(['date_from' => null], ['date_from' => 'required']);
        expect($validator->errors()->first('date_from'))->toBe(__('validation.required', ['attribute' => __('validation.attributes.date_from')]))
            ->and($validator->getData()['date_from'])->toBeNull();
    }
});

test('flat json catalogues cannot hide duplicate keys', function (): void {
    foreach (['en', 'lt', 'ru'] as $locale) {
        $json = file_get_contents(lang_path($locale.'.json'));
        preg_match_all('/^\s*("(?:[^"\\\\]|\\\\.)*")\s*:/m', $json, $matches);
        $keys = array_map(fn (string $key): string => json_decode($key, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
        expect(count($keys))->toBe(count(array_unique($keys)));
    }
});

function catalogueParameters(string $key, array|string $rules): array
{
    foreach (is_array($rules) ? $rules : explode('|', $rules) as $rule) {
        if (is_string($rule) && str_starts_with($rule, explode('.', $key)[0].':')) {
            return str_getcsv(explode(':', $rule, 2)[1], escape: '');
        }
    }

    return [];
}

function validationCatalogueCases(): array
{
    $cases = [];
    $add = function (string $key, mixed $value, mixed $rule, array $extra = [], string $field = 'date_from') use (&$cases): void {
        $cases[$key] = [$key, function () use ($value, $rule, $extra, $field): array {
            return [[$field => $value instanceof Closure ? $value() : $value, ...$extra], [$field => $rule instanceof Closure ? $rule() : $rule], $field];
        }];
    };
    foreach ([
        'accepted' => [false, 'accepted'], 'accepted_if' => [false, 'accepted_if:other,yes'],
        'active_url' => ['not a url', 'active_url'], 'after' => ['2026-01-01', 'after:2026-01-02'],
        'after_or_equal' => ['2026-01-01', 'after_or_equal:2026-01-02'],
        'alpha' => ['123', 'alpha'], 'alpha_dash' => ['!', 'alpha_dash'], 'alpha_num' => ['!', 'alpha_num'],
        'array' => ['text', 'array'], 'array_keys' => [['secret' => 1], 'array_keys:allowed'],
        'ascii' => ['Ž', 'ascii'], 'base64' => ['???', 'base64'],
        'before' => ['2026-01-03', 'before:2026-01-02'], 'before_or_equal' => ['2026-01-03', 'before_or_equal:2026-01-02'],
        'boolean' => ['false', 'boolean'], 'confirmed' => ['x', 'confirmed'],
        'contains' => [['a'], 'contains:b'], 'current_password' => ['wrong-password', 'current_password'],
        'date' => ['abc', 'date'], 'date_equals' => ['2026-01-01', 'date_equals:2026-01-02'], 'date_format' => ['bad', 'date_format:Y-m-d'],
        'decimal' => ['1.234', 'decimal:2'], 'declined' => [true, 'declined'], 'declined_if' => [true, 'declined_if:other,yes'],
        'different' => ['yes', 'different:other'], 'digits' => ['12', 'digits:3'], 'digits_between' => ['1', 'digits_between:2,3'],
        'doesnt_contain' => [['a'], 'doesnt_contain:a'], 'doesnt_end_with' => ['aaa', 'doesnt_end_with:a'], 'doesnt_start_with' => ['aaa', 'doesnt_start_with:a'],
        'email' => ['bad', 'email'], 'encoding' => ["\xff", 'encoding:UTF-8'], 'ends_with' => ['a', 'ends_with:b'],
        'exists' => [123, 'exists:fixtures,id'], 'file' => ['text', 'file'], 'filled' => ['', 'filled'],
        'hex_color' => ['x', 'hex_color'], 'in' => ['a', 'in:b'], 'in_array' => ['x', 'in_array:choices.*'],
        'in_array_keys' => [['a' => 1], 'in_array_keys:b'], 'integer' => ['abc', 'integer'],
        'ip' => ['bad', 'ip'], 'ipv4' => ['bad', 'ipv4'], 'ipv6' => ['bad', 'ipv6'], 'json' => ['bad', 'json'],
        'list' => [['a' => 1], 'list'], 'lowercase' => ['ABC', 'lowercase'], 'mac_address' => ['bad', 'mac_address'],
        'max_digits' => ['123', 'max_digits:2'], 'min_digits' => ['1', 'min_digits:2'],
        'missing' => ['x', 'missing'], 'missing_if' => ['x', 'missing_if:other,yes'], 'missing_unless' => ['x', 'missing_unless:other,no'],
        'missing_with' => ['x', 'missing_with:other'], 'missing_with_all' => ['x', 'missing_with_all:other'],
        'multiple_of' => [3, 'multiple_of:2'], 'not_in' => ['a', 'not_in:a'], 'not_regex' => ['abc', 'not_regex:/abc/'], 'numeric' => ['abc', 'numeric'],
        'prohibited' => ['x', 'prohibited'], 'prohibited_if' => ['x', 'prohibited_if:other,yes'], 'prohibited_unless' => ['x', 'prohibited_unless:other,no'],
        'prohibited_if_accepted' => ['x', 'prohibited_if_accepted:other'], 'prohibits' => ['x', 'prohibits:other'],
        'regex' => ['xyz', 'regex:/abc/'], 'required' => [null, 'required'], 'required_array_keys' => [['a' => 1], 'required_array_keys:b'],
        'required_if' => [null, 'required_if:other,yes'], 'required_if_accepted' => [null, 'required_if_accepted:other'],
        'required_unless' => [null, 'required_unless:other,no'], 'required_with' => [null, 'required_with:other'], 'required_with_all' => [null, 'required_with_all:other'],
        'same' => ['x', 'same:other'], 'starts_with' => ['a', 'starts_with:b'], 'string' => [1, 'string'], 'timezone' => ['bad', 'timezone'],
        'unique' => ['x', 'unique:fixtures,id'], 'uppercase' => ['abc', 'uppercase'], 'url' => ['bad', 'url'], 'ulid' => ['bad', 'ulid'], 'uuid' => ['bad', 'uuid'],
    ] as $key => [$value, $rule]) {
        $add($key, $value, $rule, ['other' => 'yes', 'choices' => ['a']]);
    }
    foreach (['required_without', 'required_without_all'] as $key) {
        $add($key, null, $key.':other');
    }
    foreach (['prohibited_if_declined', 'required_if_declined'] as $key) {
        $add($key, $key === 'required_if_declined' ? null : 'x', $key.':other', ['other' => 'no']);
    }
    foreach (['present', 'present_if', 'present_unless', 'present_with', 'present_with_all'] as $key) {
        $rule = match ($key) {
            'present_if' => 'present_if:other,yes', 'present_unless' => 'present_unless:other,no', 'present_with','present_with_all' => $key.':other', default => $key
        };
        $cases[$key] = [$key, fn () => [['other' => 'yes'], ['date_from' => $rule], 'date_from']];
    }
    $add('can', 'x', fn () => [Rule::can('catalogue-denied')]);
    $add('any_of', 'a', fn () => [Rule::anyOf([['integer'], ['email']])]);
    $add('enum', 'xx', fn () => [Rule::enum(SupportedLocale::class)]);
    $cases['distinct'] = ['distinct', fn () => [['dates' => ['x', 'x']], ['dates.*' => 'distinct'], 'dates.0']];
    foreach (['image' => 'image', 'mimes' => 'mimes:png', 'mimetypes' => 'mimetypes:image/png', 'extensions' => 'extensions:png'] as $key => $rule) {
        $add($key, fn () => UploadedFile::fake()->createWithContent('text.txt', 'plain text'), $rule);
    }
    $add('dimensions', fn () => UploadedFile::fake()->image('tiny.png', 2, 2), 'dimensions:min_width=10');
    $add('uploaded', fn () => new UploadedFile('/missing-upload', 'image.png', 'image/png', UPLOAD_ERR_INI_SIZE, true), 'file');
    foreach (['letters' => '12345678', 'mixed' => 'abcdefgh', 'numbers' => 'abcdefgh', 'symbols' => 'abcdefgh', 'uncompromised' => 'abcdefgh'] as $key => $value) {
        $add('password.'.$key, $value, fn () => [match ($key) {
            'letters' => Password::min(8)->letters(), 'mixed' => Password::min(8)->mixedCase(), 'numbers' => Password::min(8)->numbers(), 'symbols' => Password::min(8)->symbols(), 'uncompromised' => Password::min(8)->uncompromised(),
        }]);
    }
    foreach (['between', 'gt', 'gte', 'lt', 'lte', 'max', 'min', 'size'] as $rule) {
        $size = in_array($rule, ['lt', 'lte', 'max']) ? 4 : 1;
        foreach (['array', 'file', 'numeric', 'string'] as $type) {
            $value = match ($type) {
                'array' => array_fill(0, $size, 'a'), 'file' => fn () => UploadedFile::fake()->create('file.txt', $size), 'numeric' => $size, 'string' => str_repeat('a', $size)
            };
            $add($rule.'.'.$type, $value, [$type, $rule.($rule === 'between' ? ':2,3' : ':2')]);
        }
    }

    return $cases;
}
