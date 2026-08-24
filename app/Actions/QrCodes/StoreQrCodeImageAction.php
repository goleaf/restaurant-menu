<?php

declare(strict_types=1);

namespace App\Actions\QrCodes;

use App\Models\QrCode;
use App\Services\QrCodeSvgRenderer;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use RuntimeException;

final class StoreQrCodeImageAction
{
    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly QrCodeSvgRenderer $qrCodeSvgRenderer,
    ) {}

    public function handle(QrCode $qrCode): string
    {
        $path = $this->pathFor($qrCode);
        $publicUrl = route('public.qr.show', ['token' => $qrCode->public_token]);
        $svg = $this->qrCodeSvgRenderer->render($publicUrl);
        $disk = $this->filesystem->disk('public');

        if ($disk->exists($path) && $disk->get($path) === $svg) {
            return $path;
        }

        if (! $disk->put($path, $svg, 'public')) {
            throw new RuntimeException("Unable to store the QR image at [{$path}].");
        }

        return $path;
    }

    public function pathFor(QrCode $qrCode): string
    {
        $digest = hash('sha256', (string) $qrCode->public_token);

        return 'qr/'.substr($digest, 0, 2).'/'.$digest.'.svg';
    }

    public function delete(QrCode $qrCode): bool
    {
        $disk = $this->filesystem->disk('public');
        $path = $this->pathFor($qrCode);

        return ! $disk->exists($path) || $disk->delete($path);
    }
}
