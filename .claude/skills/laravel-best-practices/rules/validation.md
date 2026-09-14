# Validation & Forms Best Practices

## Use Form Request Classes

Extract validation from controllers into dedicated Form Request classes.

Incorrect:
```php
public function store(Request $request)
{
    $request->validate([
        'title' => 'required|max:255',
        'body' => 'required',
    ]);
}
```

Correct:
```php
public function store(StorePostRequest $request, CreatePostAction $create): RedirectResponse
{
    $post = $create->handle($request->validated());

    return to_route('posts.show', $post);
}
```

## Array vs. String Notation for Rules

Array syntax is more readable and composes cleanly with `Rule::` objects. Prefer it in new code, but check existing Form Requests first and match whatever notation the project already uses.

```php
// Preferred for new code
'email' => ['required', 'email', Rule::unique('users')],

// Follow existing convention if the project uses string notation
'email' => 'required|email|unique:users',
```

## Always Use `validated()`

Get only validated data. Never use `$request->all()` for mass operations.

Incorrect:
```php
Post::create($request->all());
```

Correct:
```php
Post::create($request->validated());
```

## Use `Rule::when()` for Conditional Validation

```php
'company_name' => [
    Rule::when($this->account_type === 'business', ['required', 'string', 'max:255']),
],
```

## Use the `after()` Method for Custom Validation

Use `after()` for cross-field Form Request validation. Check prior validation errors before reading dependent values. Database-dependent stock/tenant invariants must be scoped and revalidated inside the Action transaction; an unscoped `Product::find()` in validation cannot authorize or serialize a purchase.

```php
public function after(): array
{
    return [
        function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Check already type-validated fields with a localized error key.
        },
    ];
}
```
