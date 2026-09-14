# Routing & Controllers Best Practices

## Use Implicit Route Model Binding

Let Laravel resolve models automatically from route parameters.

Incorrect:
```php
public function show(int $id)
{
    $post = Post::findOrFail($id);
}
```

Correct:
```php
public function show(Post $post)
{
    return view('posts.show', ['post' => $post]);
}
```

## Use Scoped Bindings for Nested Resources

Enforce parent-child relationships automatically.

```php
Route::middleware('auth')->prefix('users')->name('users.')->scopeBindings()->group(function (): void {
    Route::get('/{user}/posts/{post}', ShowPostController::class)->name('posts.show');
});
```

## Use Resource Controllers

Use existing named Blade/Livewire endpoints and invokable controllers in this application. Resource routing is appropriate only when it matches an approved HTTP contract; do not add a JSON API or move business logic into route closures.

Use route groups with explicit middleware, prefix and name prefixes, as in the scoped-binding example above.

## Keep Controllers Thin

Aim for under 10 lines per method. Extract business logic to action or service classes.

Incorrect:
```php
public function store(Request $request)
{
    $validated = $request->validate([...]);
    if ($request->hasFile('image')) {
        $request->file('image')->move(public_path('images'));
    }
    $post = Post::create($validated);
    $post->tags()->sync($validated['tags']);
    event(new PostCreated($post));
    return redirect()->route('posts.show', $post);
}
```

Correct:
```php
public function store(StorePostRequest $request, CreatePostAction $create)
{
    $post = $create->handle($request->validated());

    return redirect()->route('posts.show', $post);
}
```

## Type-Hint Form Requests

Type-hinting Form Requests triggers automatic validation and authorization before the method executes.

Incorrect:
```php
public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'title' => ['required', 'max:255'],
        'body' => ['required'],
    ]);

    Post::create($validated);

    return redirect()->route('posts.index');
}
```

Correct:
```php
public function store(StorePostRequest $request, CreatePostAction $create): RedirectResponse
{
    $create->handle($request->validated());

    return redirect()->route('posts.index');
}
```
