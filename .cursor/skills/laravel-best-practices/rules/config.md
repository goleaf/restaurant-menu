<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Configuration Best Practices

## `env()` Only in Config Files

Direct `env()` calls may return `null` when config is cached.

Incorrect:
```php
$key = env('API_KEY');
```

Correct:
```php
// config/services.php
'key' => env('API_KEY'),

// Application code
$key = config('services.key');
```

## Use Encrypted Env or External Secrets

Never store production secrets in plain `.env` files in version control.

Incorrect:
```bash

# .env committed to repo or shared in Slack

STRIPE_SECRET=sk_live_abc123
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI
```

Correct:
```bash
php artisan env:encrypt --env=production --readable
php artisan env:decrypt --env=production
```

For cloud deployments, prefer the platform's native secret store (AWS Secrets Manager, Vault, etc.) and inject at runtime.

## Use `App::environment()` for Environment Checks

Incorrect:
```php
if (env('APP_ENV') === 'production') {
```

Correct:
```php
if (app()->isProduction()) {
// or
if (App::environment('production')) {
```

## Use Constants and Language Files

Use class constants instead of hardcoded magic strings for model states, types, and statuses.

```php
// Incorrect
return $this->type === 'normal';

// Correct
return $this->type === self::TYPE_NORMAL;
```

If the application already uses language files for localization, use `__()` for user-facing strings too. Do not introduce language files purely for English-only apps — simple string literals are fine there.

```php
// Only when lang files already exist in the project
return back()->with('message', __('app.article_added'));
```
