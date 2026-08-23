<?php

declare(strict_types=1);

namespace App\Actions\QrCodes;

use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Services\QrCodeSvgRenderer;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

final class StoreDemoQrCodeImageAction
{
    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly QrCodeSvgRenderer $qrCodeSvgRenderer,
    ) {}

    public function handle(QrCode $qrCode, ServicePoint $servicePoint): string
    {
        if ((int) $qrCode->service_point_id !== $servicePoint->getKey()) {
            throw new LogicException('The QR code does not belong to the supplied service point.');
        }

        $internalCode = Str::slug((string) $servicePoint->internal_code);

        if ($internalCode === '') {
            throw new LogicException('A stable service point internal code is required to store its QR image.');
        }

        $path = "demo/qr/{$internalCode}.svg";
        $publicUrl = route('public.qr.show', ['token' => $qrCode->public_token]);
        $stored = $this->filesystem
            ->disk('public')
            ->put($path, $this->qrCodeSvgRenderer->render($publicUrl), 'public');

        if (! $stored) {
            throw new RuntimeException("Unable to store the QR image at [{$path}].");
        }

        return $path;
    }
}
