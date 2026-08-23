<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}">
    <head>
        <meta charset="utf-8">
        <title>{{ __('qr.print.pdf_title', ['branch' => $branchName]) }}</title>
        <style>
            @page { margin: 10mm; }
            * { box-sizing: border-box; }
            body { margin: 0; color: {{ $theme['text'] }}; font-family: "DejaVu Sans", sans-serif; font-size: 11pt; }
            .sheet { width: 100%; border-collapse: separate; border-spacing: 6mm; table-layout: fixed; }
            .label { width: 50%; padding: 7mm; border: 1.5pt solid {{ $theme['border'] }}; background: {{ $theme['background'] }}; text-align: center; page-break-inside: avoid; vertical-align: top; }
            .brand { margin: 0 0 3mm; color: {{ $theme['accent'] }}; font-size: 15pt; font-weight: 700; }
            .instruction { margin: 0 0 4mm; font-size: 10pt; }
            .qr { width: 55mm; height: 55mm; }
            .code { margin-top: 3mm; font-size: 14pt; font-weight: 700; letter-spacing: 1pt; }
            .table-number { margin-top: 2mm; font-size: 11pt; font-weight: 700; }
        </style>
    </head>
    <body>
        <table class="sheet">
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $item)
                        <td class="label">
                            <p class="brand">{{ $branchName }}</p>
                            <p class="instruction">{{ __('qr.print.sticker_title') }}</p>
                            <img class="qr" src="{{ $item['qr_image_data_uri'] }}" alt="{{ __('qr.labels.image') }}" width="208" height="208">
                            <div class="code">{{ $item['short_code'] }}</div>
                            @if ($printTableNumber)
                                <div class="table-number">{{ __('qr.labels.table') }}: {{ $item['service_point_label'] }}</div>
                            @endif
                        </td>
                    @endforeach
                    @if (count($row) === 1)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </table>
    </body>
</html>
