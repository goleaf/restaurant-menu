<!doctype html>
<html lang="{{ __('meta.document_language') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $status }} - {{ $title }}</title>
</head>
<body style="margin:0;font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#fafaf9;color:#18181b;">
    <main style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;">
        <section style="width:100%;max-width:640px;border:1px solid #e4e4e7;border-radius:8px;background:#fff;padding:24px;">
            <div style="margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:14px;font-weight:700;">
                <x-app-logo-icon :decorative="false" :label="__('layout.app_name')" width="32" height="32" />
                <span>{{ __('layout.app_name') }}</span>
            </div>
            <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#71717a;">{{ $status }}</p>
            <h1 style="margin:0;font-size:28px;line-height:1.2;color:#18181b;">{{ $title }}</h1>
            <p style="margin:12px 0 0;font-size:16px;line-height:1.6;color:#3f3f46;">{{ $message }}</p>

            @if (! empty($hint))
                <p style="margin:12px 0 0;font-size:14px;line-height:1.6;color:#71717a;">{{ $hint }}</p>
            @endif

            <div style="margin-top:24px;display:flex;flex-wrap:wrap;gap:12px;">
                <a href="{{ route('home') }}" style="display:inline-flex;min-height:40px;align-items:center;border-radius:8px;background:#18181b;padding:0 14px;color:#fff;text-decoration:none;font-size:14px;font-weight:600;">
                    {{ __('errors.actions.home') }}
                </a>

                @auth
                    <a href="{{ route('dashboard') }}" style="display:inline-flex;min-height:40px;align-items:center;border-radius:8px;border:1px solid #d4d4d8;padding:0 14px;color:#18181b;text-decoration:none;font-size:14px;font-weight:600;">
                        {{ __('errors.actions.dashboard') }}
                    </a>
                @endauth
            </div>
        </section>
    </main>
</body>
</html>
