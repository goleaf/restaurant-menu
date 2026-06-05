@props([
    'name' => null,
    'error' => null,
])

@php
    $message = $error ?? ($name && isset($errors) ? $errors->first($name) : null);
@endphp

@if ($message)
    <p {{ $attributes->class('text-sm font-medium text-red-600 dark:text-red-300') }}>
        {{ $message }}
    </p>
@endif
