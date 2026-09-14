# Database Performance Best Practices

## Always Eager Load Relationships

Lazy loading causes N+1 query problems — one query per loop iteration. Always use `with()` to load relationships upfront.

Incorrect (N+1 — executes 1 + N queries):
```php
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->author->name;
}
```

Correct (a typical non-empty page uses 3 queries: count, posts, authors):
```php
$posts = Post::query()->select(['id', 'user_id', 'title'])->with('author:id,name')->paginate(25);
foreach ($posts as $post) {
    echo $post->author->name;
}
```

Constrain eager loads to select only needed columns (always include the foreign key):

```php
$users = User::query()->select(['id', 'name'])->with(['posts' => function ($query) {
    $query->select('id', 'user_id', 'title')
          ->where('published', true)
          ->latest()
          ->limit(10);
}])->paginate(25);
```

## Prevent Lazy Loading in Development

Enable this in `AppServiceProvider::boot()` to catch N+1 issues during development.

```php
public function boot(): void
{
    Model::preventLazyLoading(! app()->isProduction());
}
```

Throws `LazyLoadingViolationException` when a relationship is accessed without being eager-loaded.

## Select Only Needed Columns

Avoid `SELECT *` — especially when tables have large text or JSON columns.

Incorrect:
```php
$posts = Post::with('author')->get();
```

Correct:
```php
$posts = Post::select('id', 'title', 'user_id', 'created_at')
    ->with(['author:id,name,avatar'])
    ->paginate(25);
```

When selecting columns on eager-loaded relationships, always include the foreign key column or the relationship won't match.

## Chunk Large Datasets

Never load thousands of records at once. Use chunking for batch processing.

Incorrect:
```php
$users = User::all();
foreach ($users as $user) {
    $user->notify(new WeeklyDigest);
}
```

Correct:
```php
User::where('subscribed', true)->chunk(200, function ($users) {
    foreach ($users as $user) {
        $user->notify(new WeeklyDigest);
    }
});
```

Use `chunkById()` when modifying records during iteration — standard `chunk()` uses OFFSET which shifts when rows change:

```php
User::where('active', false)->chunkById(200, function ($users) {
    $users->each->delete();
});
```

## Add Database Indexes

Index columns that appear in `WHERE`, `ORDER BY`, `JOIN`, and `GROUP BY` clauses.

Incorrect:
```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('status');
    $table->timestamps();
});
```

Correct:
```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->index()->constrained();
    $table->string('status');
    $table->timestamps();
    $table->index(['status', 'created_at']);
});
```

Add composite indexes for common query patterns (e.g., `WHERE status = ? ORDER BY created_at`). This composite also covers its leading `status` column, so a separate non-unique `status` index is redundant.

## Use `withCount()` for Counting Relations

Never load entire collections just to count them.

Incorrect:
```php
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->comments->count();
}
```

Correct:
```php
$posts = Post::query()->select(['id', 'title'])->withCount('comments')->paginate(25);
foreach ($posts as $post) {
    echo $post->comments_count;
}
```

Conditional counting:

```php
$posts = Post::query()->select(['id', 'title'])->withCount([
    'comments',
    'comments as approved_comments_count' => function ($query) {
        $query->where('approved', true);
    },
])->paginate(25);
```

## Use `cursor()` for Memory-Efficient Iteration

For read-only iteration over large result sets, `cursor()` loads one record at a time via a PHP generator.

Incorrect:
```php
$users = User::where('active', true)->get();
```

Correct:
```php
foreach (User::where('active', true)->cursor() as $user) {
    ProcessUser::dispatch($user->id);
}
```

Use `cursor()` for read-only iteration. Use `chunk()` / `chunkById()` when modifying records.

## No Queries in Blade Templates

Never execute queries in Blade templates. Pass data from controllers.

Incorrect:
```blade
@foreach (User::all() as $user)
    {{ $user->profile->name }}
@endforeach
```

Correct:
```php
// Focused read service prepares a bounded, eager-loaded paginator.
$users = User::query()->select(['id', 'name'])->with('profile')->paginate(25)->withQueryString();

// Controller receives the prepared paginator.
return view('users.index', compact('users'));
```

```blade
@foreach ($users as $user)
    {{ $user->profile->name }}
@endforeach
```
