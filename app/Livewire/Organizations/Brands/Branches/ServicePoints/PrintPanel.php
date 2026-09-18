<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\ServicePoints;

use App\Actions\QrCodes\BuildQrLabelsPdfAction;
use App\Enums\QrLabelPreset;
use App\Livewire\Forms\Floor\PrintForm;
use App\Models\Branch;
use App\Services\Branches\ServicePointQueryService;
use App\Services\QrCodes\QrPrintSnapshotQuery;
use App\Support\Floor\PreparedFloorPrintStore;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintPanel extends Component
{
    use InteractsWithFloorContext;

    /** @var list<int> */
    #[Locked]
    public array $ids = [];

    /** @var array<int,int> */
    #[Locked]
    public array $expectedQrIds = [];

    #[Locked]
    public ?string $previewId = null;

    public PrintForm $form;

    private QrPrintSnapshotQuery $snapshots;

    private PreparedFloorPrintStore $prepared;

    private ServicePointQueryService $points;

    private Translator $translator;

    public function boot(QrPrintSnapshotQuery $snapshots, PreparedFloorPrintStore $prepared, ServicePointQueryService $points, Translator $translator): void
    {
        $this->snapshots = $snapshots;
        $this->prepared = $prepared;
        $this->points = $points;
        $this->translator = $translator;
    }

    /** @param list<int> $ids @param array<int,int> $expectedQrIds */
    public function mount(int $branchId, array $ids, array $expectedQrIds = []): void
    {
        $this->branchId = $branchId;
        $this->ids = $ids;
        $this->expectedQrIds = $expectedQrIds;
        $this->points->selected($this->authorizePrint(), $ids);
        $this->form->locale = $this->translator->getLocale();
    }

    public function preparePrint(): void
    {
        $branch = $this->authorizePrint();
        $data = $this->form->payload();
        $snapshot = $this->snapshots->prepare($this->actor(), $branch, $this->ids, QrLabelPreset::from($data['preset']), $data['printTableNumber'], $data['locale'], $this->expectedQrIds);
        $this->prepared->forget($this->actor(), $branch, $this->previewId);
        $this->previewId = $this->prepared->put($this->actor(), $branch, $snapshot);
        $this->dispatch('floor-editor-saved');
    }

    public function reviewQr(int $pointId): void
    {
        $branch = $this->authorizePrint();
        abort_unless(in_array($pointId, $this->ids, true), 403);
        $this->points->findForBranch($branch, $pointId);
        $this->prepared->forget($this->actor(), $branch, $this->previewId);
        $this->previewId = null;
        $this->dispatch('floor-print-recover-qr', pointId: $pointId);
    }

    public function downloadPdf(BuildQrLabelsPdfAction $build): StreamedResponse
    {
        $branch = $this->authorizePrint();
        $snapshot = $this->reviewed($branch);
        $pdf = $build->handle($this->actor(), $branch, $this->ids, QrLabelPreset::from($snapshot['preset']), $snapshot['print_table_number'], $snapshot);

        return response()->streamDownload(static function () use ($pdf): void {
            echo $pdf['contents'];
        }, $pdf['filename'], ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function printLabels(): void
    {
        $branch = $this->authorizePrint();
        $snapshot = $this->reviewed($branch);
        $this->snapshots->reviewed($this->actor(), $branch, $this->ids, QrLabelPreset::from($snapshot['preset']), $snapshot['print_table_number'], $snapshot);
        $this->dispatch('floor-print-ready');
    }

    public function cancelPrint(): void
    {
        $branch = $this->authorizePrint();
        $this->prepared->forget($this->actor(), $branch, $this->previewId);
        $this->previewId = null;
        $this->resetValidation();
        $this->dispatch('floor-editor-cancelled');
    }

    public function render(): View
    {
        $branch = $this->authorizePrint();
        $snapshot = $this->prepared->find($this->actor(), $branch, $this->previewId);
        try {
            $availability = $this->snapshots->availability($this->actor(), $branch, $this->ids, $this->expectedQrIds);
        } catch (ValidationException $exception) {
            $availability = [];
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        }

        return view('livewire.organizations.brands.branches.service-points.print-panel', [
            'selectedCount' => count($this->ids), 'snapshot' => $snapshot, 'presetOptions' => QrLabelPreset::options(),
            'availability' => $availability,
            'presetClass' => $snapshot === null ? '' : QrLabelPreset::from($snapshot['preset'])->cssClass(),
            'printHeading' => $snapshot === null ? '' : __('qr.print.pdf_title', ['branch' => $snapshot['branch_name']], $snapshot['locale']),
            'stickerTitle' => $snapshot === null ? '' : __('qr.print.sticker_title', [], $snapshot['locale']),
            'tableLabel' => $snapshot === null ? '' : __('qr.labels.table', [], $snapshot['locale']),
        ]);
    }

    private function authorizePrint(): Branch
    {
        $branch = $this->branch();
        Gate::forUser($this->actor())->authorize('generateQr', $branch);

        return $branch;
    }

    /** @return array<string,mixed> */
    private function reviewed(Branch $branch): array
    {
        $data = $this->form->payload();
        $snapshot = $this->prepared->find($this->actor(), $branch, $this->previewId);
        if ($snapshot === null) {
            throw ValidationException::withMessages(['previewId' => __('floor.print.expired')]);
        }
        if ($data['preset'] !== $snapshot['preset'] || $data['locale'] !== $snapshot['locale'] || $data['printTableNumber'] !== $snapshot['print_table_number']) {
            throw ValidationException::withMessages(['service_points' => __('floor.validation.print_changed')]);
        }

        return $snapshot;
    }
}
