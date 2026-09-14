# Eloquent Best Practices

## Use Correct Relationship Types

Use `hasMany`, `belongsTo`, `morphMany`, etc. with proper return type hints.

```php
public function comments(): HasMany
{
    return $this->hasMany(Comment::class);
}

public function author(): BelongsTo
{
    return $this->belongsTo(User::class, 'user_id');
}
```

## Use Local Scopes for Reusable Queries

Extract reusable query constraints into local scopes to avoid duplication.

Incorrect:
```php
$active = User::where('verified', true)->whereNotNull('activated_at')->get();
$articles = Article::whereHas('user', function ($q) {
    $q->where('verified', true)->whereNotNull('activated_at');
})->get();
```

Correct:
```php
#[Scope]
protected function active(Builder $query): Builder
{
    return $query->where('verified', true)->whereNotNull('activated_at');
}

// Usage
$active = User::active()->get();
$articles = Article::whereHas('user', fn ($q) => $q->active())->get();
```

## Apply Global Scopes Sparingly

Global scopes silently modify every query on the model, making debugging difficult. Prefer local scopes and reserve global scopes for truly universal constraints like soft deletes or multi-tenancy.

Incorrect (global scope for a conditional filter):
```php
class PublishedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('published', true);
    }
}
// Now admin panels, reports, and background jobs all silently skip drafts
```

Correct (local scope you opt into):
```php
#[Scope]
protected function published(Builder $query): Builder
{
    return $query->where('published', true);
}

Post::published()->paginate(); // Explicit
Post::paginate(); // Admin sees all
```

## Define Attribute Casts

Use the repository's `casts()` method for automatic type conversion.

```php
protected function casts(): array
{
    return [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'total_price_cents' => 'integer',
    ];
}
```

## Cast Date Columns Properly

Always cast date columns. Prepare locale/timezone-aware date labels in the existing presentation boundary before rendering; Blade displays the prepared value.

Incorrect:
```blade
{{ Carbon::createFromFormat('Y-d-m H-i', $order->ordered_at)->toDateString() }}
```

Correct:
```php
protected function casts(): array
{
    return [
        'ordered_at' => 'datetime',
    ];
}
```

```blade
{{ $orderedAtLabel }}
```

## Use `whereBelongsTo()` for Relationship Queries

Cleaner than manually specifying foreign keys.

Incorrect:
```php
Post::where('user_id', $user->id)->get();
```

Correct:
```php
Post::whereBelongsTo($user)->get();
Post::whereBelongsTo($user, 'author')->get();
```

## Keep Persistence in Eloquent

Use models, relationships and reusable scopes for all first-party reads and writes. `DB::table()`, raw SQL and raw expression methods are not alternative paths in this repository. Schema Builder table names remain appropriate inside migrations; data backfills use the existing Eloquent migration pattern and receive a fresh-install/rollback test. Preserve deployed migrations rather than rewriting historical backfills when models evolve.
