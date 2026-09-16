<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}">
    <head>
        <meta charset="utf-8">
        <title>{{ __('qr.print.pdf_title', ['branch' => $branchName]) }}</title>
        <style>
            @include('generated.styles.pdf-qr')
        </style>
    </head>
    <body data-qr-preset="{{ $preset }}">
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
