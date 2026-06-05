@php
    $isGuestSurface = request()->is('q/*') || request()->is('guest*');
@endphp

@include('errors.shell', [
    'status' => 500,
    'title' => $isGuestSurface ? __('errors.guest.system.title') : __('errors.admin.system.title'),
    'message' => $isGuestSurface ? __('errors.guest.system.message') : __('errors.admin.system.message'),
    'hint' => $isGuestSurface ? __('errors.guest.system.hint') : __('errors.admin.system.hint'),
])
