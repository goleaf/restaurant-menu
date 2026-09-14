# Blade & Views Best Practices

## Merge Component Attributes with Static Classes

Use the component attribute bag so consumers can add classes. `class()` combines complete static utility names and presentation conditions without constructing undiscoverable Tailwind fragments.

```blade
<div {{ $attributes->class(['rounded-lg border p-4', 'border-red-600' => $type === 'error']) }}>
    {{ $message }}
</div>
```

## Use `@pushOnce` for Per-Component Scripts

If a component renders inside a `@foreach`, `@push` inserts the script N times. `@pushOnce` guarantees it's included exactly once.

## Prefer Blade Components Over `@include`

`@include` shares all parent variables implicitly (hidden coupling). Components have explicit props, attribute bags, and slots.

## Use View Composers for Shared View Data

If every controller rendering a sidebar must pass `$categories`, that's duplicated code. A View Composer centralizes it.

## Preserve the Livewire Rendering Boundary

Livewire owns interactive updates here. Do not introduce htmx/Turbo to perform partial re-renders. Static presentation reuse stays in Blade components, with prepared values and statically discoverable Tailwind classes.

## Use `@aware` for Deeply Nested Component Props

Avoids re-passing parent props through every level of nested components.
