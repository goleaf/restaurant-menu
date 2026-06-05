@php
    $isGuestSurface = request()->is('q/*') || request()->is('guest*');
@endphp

@include('errors.shell', [
    'status' => 403,
    'title' => $isGuestSurface ? __('errors.guest.permission_denied.title') : __('errors.admin.permission_denied.title'),
    'message' => $isGuestSurface ? __('errors.guest.permission_denied.message') : __('errors.admin.permission_denied.message'),
    'hint' => $isGuestSurface ? __('errors.guest.permission_denied.hint') : __('errors.admin.permission_denied.hint'),
])
