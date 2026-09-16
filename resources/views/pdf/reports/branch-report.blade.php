<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}">
    <head>
        <meta charset="utf-8">
        <title>{{ $reportTitle }} — {{ $branchName }}</title>
        <style>
            @include('generated.styles.pdf-report')
        </style>
    </head>
    <body>
        <header>
            <h1>{{ $reportTitle }}</h1>
            <p class="subtitle">{{ $branchName }}</p>
        </header>

        <table class="meta">
            <tr>
                <td>
                    <span class="meta-label">{{ __('reports.pdf.period') }}</span>
                    <span class="meta-value">{{ $period }}</span>
                </td>
                <td>
                    <span class="meta-label">{{ __('reports.pdf.generated_at') }}</span>
                    <span class="meta-value">{{ $generatedAt }}</span>
                </td>
                <td>
                    <span class="meta-label">{{ __('reports.pdf.records') }}</span>
                    <span class="meta-value">{{ $shownRecords }} / {{ $totalRecords }}</span>
                </td>
                @forelse ($totals as $total)
                    <td>
                        <span class="meta-label">{{ $total['label'] }}</span>
                        <span class="meta-value">{{ $total['value'] }}</span>
                    </td>
                @empty
                    <td></td>
                @endforelse
            </tr>
        </table>

        @if ($truncated)
            <p class="notice">{{ __('reports.pdf.row_limit', ['count' => $shownRecords]) }}</p>
        @endif

        @if ($hasRows)
            <table class="report">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">{{ __('reports.pdf.empty') }}</p>
        @endif
    </body>
</html>
