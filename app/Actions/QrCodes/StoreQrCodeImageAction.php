<?php

declare(strict_types=1);

namespace App\Actions\QrCodes;

use App\Models\QrCode;
use App\Services\QrCodes\PublicQrUrl;
use App\Services\QrCodeSvgRenderer;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemException;
use RuntimeException;

final class StoreQrCodeImageAction
{
    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly QrCodeSvgRenderer $qrCodeSvgRenderer,
        private readonly PublicQrUrl $publicUrl,
    ) {}

    public function handle(QrCode $qrCode): string
    {
        $path = $this->pathFor($qrCode);
        $svg = $this->qrCodeSvgRenderer->render($this->publicUrl->forToken($qrCode->public_token));
        $disk = $this->filesystem->disk('public');

        if ($disk->exists($path) && $disk->get($path) === $svg) {
            return $path;
        }

        $temporary = $path.'.'.Str::uuid().'.tmp';
        try {
            if (! $disk->put($temporary, $svg, 'public') || $disk->get($temporary) !== $svg
                || ! $disk->move($temporary, $path)) {
                throw new RuntimeException('Unable to publish a complete QR image.');
            }
        } finally {
            if ($disk->exists($temporary)) {
                $disk->delete($temporary);
            }
        }

        return $path;
    }

    public function isReady(QrCode $qrCode): bool
    {
        $disk = $this->filesystem->disk('public');
        $path = $this->pathFor($qrCode);
        try {
            return $disk->exists($path)
                && $disk->get($path) === $this->qrCodeSvgRenderer->render($this->publicUrl->forToken($qrCode->public_token));
        } catch (FilesystemException) {
            return false;
        }
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
