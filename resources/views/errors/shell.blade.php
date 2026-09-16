<!doctype html>
<html lang="{{ __('meta.document_language') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $status }} - {{ $title }}</title>
    <style>
        @include('generated.styles.emergency')
    </style>
</head>
<body class="rm-emergency">
    <main class="rm-emergency-main">
        <section class="rm-emergency-panel">
            <div class="rm-emergency-brand">
                <x-app-logo-icon :decorative="false" :label="__('layout.app_name')" width="32" height="32" />
                <span>{{ __('layout.app_name') }}</span>
            </div>
            <p class="rm-emergency-status">{{ $status }}</p>
            <h1 class="rm-emergency-title">{{ $title }}</h1>
            <p class="rm-emergency-message">{{ $message }}</p>

            @if (! empty($hint))
                <p class="rm-emergency-hint">{{ $hint }}</p>
            @endif

            <div class="rm-emergency-actions">
                <a href="{{ route('home') }}" class="rm-emergency-action">
                    {{ __('errors.actions.home') }}
                </a>

                @auth
                    <a href="{{ route('dashboard') }}" class="rm-emergency-action rm-emergency-action--secondary">
                        {{ __('errors.actions.dashboard') }}
                    </a>
                @endauth
            </div>
        </section>
    </main>
</body>
</html>
