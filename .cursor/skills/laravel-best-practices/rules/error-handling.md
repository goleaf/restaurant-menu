<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Error Handling Best Practices

## Exception Reporting and Rendering

There are two valid approaches — choose one and apply it consistently across the project.

**Co-location on the exception class** — keeps behavior alongside the exception definition, easier to find:

```php
class InvalidOrderException extends Exception
{
    public function report(): void { /* custom reporting */ }

    public function render(Request $request): Response
    {
        return response()->view('errors.invalid-order', status: 422);
    }
}
```

**Centralized in `bootstrap/app.php`** — all exception handling in one place, easier to see the full picture:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->report(function (InvalidOrderException $e) { /* ... */ });
    $exceptions->render(function (InvalidOrderException $e, Request $request) {
        return response()->view('errors.invalid-order', status: 422);
    });
})
```

Check the existing codebase and follow whichever pattern is already established.

## Use `ShouldntReport` for Exceptions That Should Never Log

More discoverable than listing classes in `dontReport()`.

```php
class PodcastProcessingException extends Exception implements ShouldntReport {}
```

## Throttle High-Volume Exceptions

A single failing integration can flood error tracking. Use `throttle()` to rate-limit per exception type.

## Enable `dontReportDuplicates()`

Prevents the same exception instance from being logged multiple times when `report($e)` is called in multiple catch blocks.

## Force JSON Error Rendering for API Routes

Laravel auto-detects `Accept: application/json` but API clients may not set it. Explicitly declare JSON rendering for API routes.

```php
$exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
    return $request->is('api/*') || $request->expectsJson();
});
```

## Add Context to Exception Classes

Attach structured data to exceptions at the source via a `context()` method — Laravel includes it automatically in the log entry.

```php
class InvalidOrderException extends Exception
{
    public function context(): array
    {
        return ['order_id' => $this->orderId];
    }
}
```

## Rollback Compensation Must Finish

The repository's image rollback boundary uses `DeleteRolledBackLocalImageAction`. Catch cleanup and diagnostic-logger failures inside that boundary so the original persistence exception survives and Laravel can discard all transaction callbacks. Attempt remaining owned files and test a subsequent independent commit; a leaked callback can delete an old image that rollback preserved. Keep ordinary deletion and after-commit failures visible so committed operations retain their existing retry contract. Do not write a cleanup receipt into the transaction being rolled back or claim that logging guarantees orphan cleanup.
