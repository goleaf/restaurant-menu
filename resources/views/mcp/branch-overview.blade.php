<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'none'; connect-src 'none'; object-src 'none'; frame-src 'none'; base-uri 'none'; form-action 'none'">
    <title>{{ __('mcp.resources.overview_title') }}</title>
</head>
<body>
    <main>
        <h1>{{ __('mcp.resources.overview_title') }}</h1>
        <dl>
            <dt>{{ __('guest.table.branch') }}</dt>
            <dd>{{ $branch['name'] }}</dd>
            <dt>{{ __('dashboard.control.timezone') }}</dt>
            <dd>{{ $branch['timezone'] }}</dd>
            <dt>{{ __('guest.table.currency') }}</dt>
            <dd>{{ $branch['currency'] }}</dd>
        </dl>
        <p>{{ __('mcp.app.read_only') }}</p>
    </main>
</body>
</html>
