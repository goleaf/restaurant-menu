<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Filesystem\Filesystem;

final class SecurePdfRenderer
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    public function render(string $html, string $paper = 'a4', string $orientation = 'portrait'): string
    {
        $temporaryDirectory = storage_path('framework/cache/dompdf');
        $this->files->ensureDirectoryExists($temporaryDirectory);

        $options = new Options;
        $options->setAllowedProtocols(['data://']);
        $options->setChroot([]);
        $options->setDefaultFont('DejaVu Sans');
        $options->setFontCache($temporaryDirectory);
        $options->setIsFontSubsettingEnabled(true);
        $options->setIsJavascriptEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsRemoteEnabled(false);
        $options->setTempDir($temporaryDirectory);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return $dompdf->output();
    }
}
