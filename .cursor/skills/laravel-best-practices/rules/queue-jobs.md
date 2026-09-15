<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Queue & Job Best Practices

This application's required workflows complete without a continuously running worker. Queue guidance applies to optional jobs with an existing bounded web/Artisan recovery path. Preserve the configured database queue; Redis/Horizon is not part of the runtime contract.

## Set `retry_after` Greater Than `timeout`

If `retry_after` is shorter than the job's `timeout`, the queue worker re-dispatches the job while it's still running, causing duplicate execution.

Incorrect (`retry_after` ≤ `timeout`):
```php
class ProcessReport implements ShouldQueue
{
    public $timeout = 120;
}

// config/queue.php — retry_after: 90 ← job retried while still running!
```

Correct (`retry_after` > `timeout`):
```php
class ProcessReport implements ShouldQueue
{
    public $timeout = 120;
}

// config/queue.php — retry_after: 180 ← safely longer than any job timeout
```

## Use Exponential Backoff

Use progressively longer delays between retries to avoid hammering failing services.

Incorrect (fixed retry interval):
```php
class SyncWithStripe implements ShouldQueue
{
    public $tries = 3;
    // Default: retries immediately, overwhelming the API
}
```

Correct (exponential backoff):
```php
class SyncWithStripe implements ShouldQueue
{
    public $tries = 3;
    public $backoff = [1, 5, 10];
}
```

## Implement `ShouldBeUnique`

Prevent dispatching another job with the same uniqueness key while its cache lock is held. Delivery/retry can still execute work again, so durable domain operations require their own idempotency constraints.

```php
class GenerateInvoice implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return (string) $this->order->id;
    }

    public $uniqueFor = 3600;
}
```

## Always Implement `failed()`

Handle errors explicitly — don't rely on silent failure.

```php
public function failed(?Throwable $exception): void
{
    $this->podcast->update(['status' => 'failed']);
    Log::error('Processing failed', ['id' => $this->podcast->id, 'exception' => $exception ? $exception::class : null]);
}
```

## Rate Limit External API Calls in Jobs

Use `RateLimited` middleware to throttle jobs calling third-party APIs.

```php
public function middleware(): array
{
    return [new RateLimited('external-api')];
}
```

## Batch Related Jobs

Use `Bus::batch()` to coordinate progress and success/failure callbacks. A failed batch does not roll back effects from jobs that already completed. Keep each job idempotent, observe cancellation, and use an Action transaction or explicit compensation for atomic domain changes.

```php
Bus::batch([
    new ImportCsvChunk($chunk1),
    new ImportCsvChunk($chunk2),
])
->then(fn (Batch $batch) => Notification::send($user, new ImportComplete))
->catch(fn (Batch $batch, Throwable $e) => Log::error('Batch failed'))
->dispatch();
```

## `retryUntil()` Defines the Retry Deadline

Laravel 13's worker checks a configured `retryUntil()` before the attempt-count limit; `$tries = 0` is not required. Choose the intended time-based or attempt-based policy and test exhaustion. Separate timeout/exception limits still apply.

```php
public function retryUntil(): \DateTimeInterface
{
    return now()->addHours(4);
}
```

## Use `ShouldBeUniqueUntilProcessing` for Early Lock Release

`ShouldBeUnique` holds the lock until the job completes. `ShouldBeUniqueUntilProcessing` releases it when processing starts, allowing new instances to queue.

```php
class UpdateSearchIndex implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    // Lock releases when processing begins, not when it finishes
}
```

## Use the Existing Operations Surface

Use the repository's safe logs, failed-job inspection and bounded optional queue commands. Do not add Horizon, Redis or Supervisor to solve an ordinary queue task. [Laravel queue attempts and batching](https://laravel.com/docs/13.x/queues).
