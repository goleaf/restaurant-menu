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

## Preserve original transport types

Validate before casting user-controlled input. Livewire PHP property declarations may coerce a boolean into a string/int or throw on a scalar/array mismatch before validation; editable raw values may need `mixed`, with typed mapping only after successful validation. Trim only strings. Keep immutable server-owned identifiers typed and locked, and still scope/authorize mutations.

Laravel's `integer` rule uses `FILTER_VALIDATE_INT`; combine it with `numeric` to reject booleans while accepting normal numeric strings. `integer:strict` rejects those browser strings and is not a drop-in replacement. Decimal money must retain its original value for `DecimalMoney` to reject native floats and booleans.

Dependent selection reads and uniqueness builders must tolerate malformed input. Use a safe scalar projection for these reads, keep the original property for its own rules, and apply `bail` before database rules when prerequisite types fail. A projection is not validation or authorization. Cover actual Livewire update payloads for both create and edit, asserting no persistence on failure and exact prices, flags and translations on success; `tests/Feature/MenuEditorTransportTest.php` is the menu regression matrix.
