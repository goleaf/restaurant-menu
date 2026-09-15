---
name: livewire-development
description: "Use for any task or question involving Livewire. Activate if user mentions Livewire, wire: directives, or Livewire-specific concepts like wire:model, wire:click, wire:sort, or islands, invoke this skill. Covers building new components, debugging reactivity issues, real-time form validation, drag-and-drop, loading states, migrating from Livewire 3 to 4, converting component formats (SFC/MFC/class-based), and performance optimization. Do not use for non-Livewire reactive UI (React, Vue, Alpine-only, Inertia.js) or standard Laravel forms without Livewire."
license: MIT
metadata:
  author: laravel
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Livewire Development

## Repository menu lifecycle boundary

For menu editor JavaScript, read `.ai/rules/js.md` and the local JavaScript-hooks reference. Verify the installed interceptor API and its unsubscribe behavior. Translated server-validated forms must declare `novalidate` in Blade so morphs cannot restore browser validation over hidden panels; required server rules stay unchanged.

## Documentation

Use `search-docs` for detailed Livewire 4 patterns and documentation.

## Basic Usage

### Creating Components

This repository requires a PHP class under `app/Livewire` and a separate presentation-only Blade view under `resources/views/livewire`. `config/livewire.php` sets `make_command.type` to `class` and disables emoji filenames. SFC, MFC and Volt are not application formats here; do not convert existing components to them.

```bash
php artisan make:livewire Posts/CreatePost --class --no-interaction
```

This creates `app/Livewire/Posts/CreatePost.php` and `resources/views/livewire/posts/create-post.blade.php`. Keep read queries in focused read services, persistent mutations in Actions, substantial validation in Livewire Forms, and server authorization on every protected mutation. Use `boot()` injection for services required after hydration.

### Class-Based Component Example

An ephemeral counter illustrates state only; persisted domain changes must follow the Action/Form/authorization boundaries above.

```php
<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Counter extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): View
    {
        return view('livewire.counter');
    }
}
```

The separate `resources/views/livewire/counter.blade.php` receives that public state:

```blade
<div>
    <button type="button" wire:click="increment">{{ __('menu.guest.add') }}</button>
    <output aria-live="polite">{{ $count }}</output>
</div>
```

## Livewire 4 Specifics

### Key Changes From Livewire 3

These things changed in Livewire 4, but may not have been updated in this application. Verify this application's setup to ensure you follow existing conventions.

- Use `Route::livewire()` for full-page components (e.g., `Route::livewire('/posts/create', CreatePost::class)`); config keys renamed: `layout` → `component_layout`, `lazy_placeholder` → `component_placeholder`.
- `wire:model` now ignores child events by default (use `wire:model.deep` for old behavior); `wire:scroll` renamed to `wire:navigate:scroll`.
- Component tags must be properly closed; `wire:transition` now uses View Transitions API (modifiers removed).
- JavaScript: `$wire.$js('name', fn)` → `$wire.$js.name = fn`; `commit`/`request` hooks → `interceptMessage()`/`interceptRequest()`.

### New Features

- Upstream also supports SFC/MFC/view-based formats; this application retains class-based components.
- Islands (`@island`) for isolated updates; async actions (`wire:click.async`, `#[Async]`) for parallel execution.
- Deferred/bundled loading: `defer`, `lazy.bundle` for optimized component loading.

| Feature | Usage | Purpose |
|---------|-------|---------|
| Islands | `@island(name: 'stats')` | Isolated update regions |
| Async | `wire:click.async` or `#[Async]` | Non-blocking actions |
| Deferred | `defer` attribute | Load after page render |
| Bundled | `lazy.bundle` | Load multiple together |

### New Directives

- `wire:sort`, `wire:intersect`, `wire:ref`, `.renderless`, `.preserve-scroll` are available for use.
- `data-loading` attribute automatically added to elements triggering network requests.

| Directive | Purpose |
|-----------|---------|
| `wire:sort` | Drag-and-drop sorting |
| `wire:intersect` | Viewport intersection detection |
| `wire:ref` | Element references for JS |
| `.renderless` | Component without rendering |
| `.preserve-scroll` | Preserve scroll position |

## Best Practices

- Always use `wire:key` in loops
- Use `wire:loading` for loading states
- Use `wire:model.live` for live updates; `wire:model` is deferred by default
- Validate and authorize in actions (treat like HTTP requests).
- Preserve original transport types on editable state until validation. PHP scalar/array declarations can coerce booleans and money or throw during hydration before a rule runs; use `mixed` for these raw inputs and typed explicit maps after validation. Keep server-owned locked context typed.
- Trim only strings. Combine `numeric` and `integer` for integer inputs that accept browser numeric strings but reject booleans. Dependent selections need safe read projections without replacing the raw value that validation checks; see [validation guidance](../laravel-best-practices/rules/validation.md).

## Configuration

- `smart_wire_keys` defaults to `true`; new configs: `component_locations`, `component_namespaces`, `make_command`, `csp_safe`.

## Alpine & JavaScript

- `wire:transition` uses browser View Transitions API; `$errors` and `$intercept` magic properties available.
- Keep polling bounded and isolate only independently useful regions. Parallel requests can race and increase server work; prove that concurrency preserves authorization, persistence and query budgets before enabling it.

For interceptors and hooks, see [reference/javascript-hooks.md](reference/javascript-hooks.md).

## Testing

<!-- Testing Example -->
```php
Livewire::test(Counter::class)
    ->assertSet('count', 0)
    ->call('increment')
    ->assertSet('count', 1);
```

## Verification

1. Browser console: Check for JS errors
2. Network tab: Verify Livewire requests return 200
3. Ensure `wire:key` on all `@foreach` loops

## Common Pitfalls

- Missing `wire:key` in loops → unexpected re-rendering
- Expecting `wire:model` real-time → use `wire:model.live`
- Unclosed component tags → syntax errors in v4
- Using deprecated config keys or JS hooks
- Including Alpine.js separately (already bundled in Livewire 4)
