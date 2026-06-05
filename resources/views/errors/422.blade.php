@php
    $isGuestSurface = request()->is('q/*') || request()->is('guest*');
@endphp

@include('errors.shell', [
    'status' => 422,
    'title' => $isGuestSurface ? __('errors.guest.validation.title') : __('errors.admin.validation.title'),
    'message' => $isGuestSurface ? __('errors.guest.validation.message') : __('errors.admin.validation.message'),
    'hint' => $isGuestSurface ? __('errors.guest.validation.hint') : __('errors.admin.validation.hint'),
])
