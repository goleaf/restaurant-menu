<?php

declare(strict_types=1);

namespace App\Actions\QrCodes;

use App\Enums\QrLabelPreset;
use App\Models\Branch;
use App\Models\User;
use App\Services\QrCodes\QrPrintSnapshotQuery;
use App\Services\SecurePdfRenderer;
use Illuminate\Contracts\Translation\Translator;

final class BuildQrLabelsPdfAction
{
    public function __construct(
        private readonly QrPrintSnapshotQuery $snapshots,
        private readonly Translator $translator,
        private readonly SecurePdfRenderer $pdfRenderer,
    ) {}

    /**
     * @param  list<int>  $servicePointIds
     * @param  array<string,mixed>|null  $reviewedSnapshot
     * @return array{contents: string, filename: string}
     */
    public function handle(
        User $user,
        Branch $branch,
        array $servicePointIds,
        QrLabelPreset $preset,
        bool $printTableNumber,
        ?array $reviewedSnapshot = null,
    ): array {
        $snapshot = $reviewedSnapshot === null
            ? $this->snapshots->prepare($user, $branch, $servicePointIds, $preset, $printTableNumber, $this->translator->getLocale())
            : $this->snapshots->reviewed($user, $branch, $servicePointIds, $preset, $printTableNumber, $reviewedSnapshot);
        $originalLocale = $this->translator->getLocale();
        $this->translator->setLocale($snapshot['locale']);
        try {
            $html = view('pdf.qr-labels', [
                'branchName' => $snapshot['branch_name'],
                'rows' => array_chunk($snapshot['items'], 2),
                'printTableNumber' => $snapshot['print_table_number'],
                'preset' => $snapshot['preset'],
            ])->render();
        } finally {
            $this->translator->setLocale($originalLocale);
        }

        return [
            'contents' => $this->pdfRenderer->render($html),
            'filename' => 'restaurant-menu-qr-branch-'.$branch->id.'-'.now()->format('Y-m-d-His').'.pdf',
        ];
    }
}
