@php
    $isGuestSurface = request()->is('q/*') || request()->is('guest*');
@endphp

@include('errors.shell', [
    'status' => 419,
    'title' => $isGuestSurface ? __('errors.guest.session_expired.title') : __('errors.admin.session_expired.title'),
    'message' => $isGuestSurface ? __('errors.guest.session_expired.message') : __('errors.admin.session_expired.message'),
    'hint' => $isGuestSurface ? __('errors.guest.session_expired.hint') : __('errors.admin.session_expired.hint'),
])
