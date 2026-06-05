@php
    $isQrSurface = request()->is('q/*');
    $isGuestSurface = $isQrSurface || request()->is('guest*');
@endphp

@include('errors.shell', [
    'status' => 404,
    'title' => $isQrSurface
        ? __('errors.guest.qr_not_found.title')
        : ($isGuestSurface ? __('errors.guest.not_found.title') : __('errors.admin.not_found.title')),
    'message' => $isQrSurface
        ? __('errors.guest.qr_not_found.message')
        : ($isGuestSurface ? __('errors.guest.not_found.message') : __('errors.admin.not_found.message')),
    'hint' => $isQrSurface
        ? __('errors.guest.qr_not_found.hint')
        : ($isGuestSurface ? __('errors.guest.not_found.hint') : __('errors.admin.not_found.hint')),
])
