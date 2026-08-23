<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}">
    <head>
        <meta charset="utf-8">
        <title>{{ $reportTitle }} — {{ $branchName }}</title>
        <style>
            @page { margin: 12mm; }
            * { box-sizing: border-box; }
            body { margin: 0; color: #18181b; font-family: "DejaVu Sans", sans-serif; font-size: 8pt; }
            h1 { margin: 0; font-size: 18pt; }
            .subtitle { margin: 2mm 0 0; color: #52525b; font-size: 10pt; }
            .meta { width: 100%; margin: 6mm 0; border-collapse: collapse; }
            .meta td { width: 25%; padding: 3mm; border: 1px solid #d4d4d8; vertical-align: top; }
            .meta-label { display: block; margin-bottom: 1mm; color: #71717a; font-size: 7pt; text-transform: uppercase; }
            .meta-value { font-size: 10pt; font-weight: 700; }
            .notice { margin: 4mm 0; padding: 3mm; border: 1px solid #f59e0b; background: #fffbeb; color: #78350f; }
            .report { width: 100%; border-collapse: collapse; table-layout: fixed; }
            .report th { padding: 2mm; border: 1px solid #a1a1aa; background: #f4f4f5; text-align: left; font-size: 7pt; }
            .report td { padding: 2mm; border: 1px solid #d4d4d8; overflow-wrap: break-word; vertical-align: top; }
            .report tr { page-break-inside: avoid; }
            .empty { padding: 8mm; border: 1px solid #d4d4d8; color: #71717a; text-align: center; }
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
