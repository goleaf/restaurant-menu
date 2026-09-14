# Security Best Practices

## Mass Assignment Protection

Every first-party model must define an intentional `$fillable` allow-list.

Incorrect:
```php
class User extends Model
{
    protected $guarded = []; // All fields are mass assignable
}
```

Correct:
```php
class User extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}
```

Never use `$guarded = []` in first-party models.

## Authorize Every Action

Use policies or gates in controllers and every protected Livewire mutation. Re-resolve the resource inside its tenant scope, then delegate persistent writes to the focused Action.

Incorrect:
```php
public function update(UpdatePostRequest $request, Post $post)
{
    $post->update($request->validated());
}
```

Correct:
```php
public function update(UpdatePostRequest $request, Post $post, UpdatePostAction $update): RedirectResponse
{
    Gate::authorize('update', $post);

    $update->handle($post, $request->validated());

    return to_route('posts.show', $post);
}
```

Or via Form Request:

```php
public function authorize(): bool
{
    return $this->user()->can('update', $this->route('post'));
}
```

## Prevent SQL Injection

Use Eloquent value bindings. Never interpolate user input into queries or use raw expression methods; bound raw SQL is still prohibited by this repository.

Incorrect: interpolating a request value into SQL. Raw expression methods are also prohibited even when their values use valid bindings.

Correct:
```php
User::query()->select(['id', 'name'])->where('name', $validatedName)->paginate(25);
```

## Escape Output to Prevent XSS

Use `{{ }}` for HTML escaping. Only use `{!! !!}` for trusted, pre-sanitized content.

Incorrect:
```blade
{!! $user->bio !!}
```

Correct:
```blade
{{ $user->bio }}
```

## CSRF Protection

Include `@csrf` in all POST/PUT/PATCH/DELETE Blade forms. Inertia doesn't use `@csrf`; its HTTP client sends the `XSRF-TOKEN` cookie back as the `X-XSRF-TOKEN` header, which Laravel accepts in place of the `_token` field.

Incorrect:
```blade
<form method="POST" action="/posts">
    <input type="text" name="title">
</form>
```

Correct:
```blade
<form method="POST" action="/posts">
    @csrf
    <input type="text" name="title">
</form>
```

## Rate Limit Auth and API Routes

Apply `throttle` middleware to authentication and API routes.

```php
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});

Route::post('/login', LoginController::class)->middleware('throttle:login');
```

## Validate File Uploads

Validate MIME type and size. Both `mimes` and `mimetypes` read the file's contents to guess its MIME type; `mimes` just expresses the allow-list as extensions. The `extensions` rule checks only the client-supplied filename, so never rely on it alone. Never trust client-provided filenames.

```php
public function rules(): array
{
    return [
        'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
    ];
}
```

Store with generated filenames:

```php
$path = $request->file('avatar')->store('avatars', 'public');
```

## Keep Secrets Out of Code

Never commit `.env`. Access secrets via `config()` only.

Incorrect:
```php
$key = env('API_KEY');
```

Correct:
```php
// config/services.php
'api_key' => env('API_KEY'),

// In application code
$key = config('services.api_key');
```

## Audit Dependencies

Run `composer audit` periodically to check for known vulnerabilities in dependencies. Automate this in CI to catch issues before deployment.

```bash
composer audit
```

## Encrypt Sensitive Database Fields

Use `encrypted` cast for API keys/tokens and mark the attribute as `hidden`.

Incorrect:
```php
class Integration extends Model
{
    protected function casts(): array
    {
        return [
            'api_key' => 'string',
        ];
    }
}
```

Correct:
```php
class Integration extends Model
{
    protected $hidden = ['api_key', 'api_secret'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
        ];
    }
}
```
