<?php

declare(strict_types=1);

namespace App\Livewire\Exports;

use App\Actions\Exports\BuildBranchPdfReportAction;
use App\Actions\Exports\BuildDataExportsIndexAction;
use App\Actions\Exports\PrepareBranchCsvExportAction;
use App\Enums\DataExportType;
use App\Livewire\Forms\Exports\ReportDownloadForm;
use App\Models\User;
use App\Services\Navigation\WorkspaceContextResolver;
use App\Support\Files\PreparedDownloadAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Index extends Component
{
    public ReportDownloadForm $period;

    #[Locked]
    public string $downloadAttempt = '';

    #[Locked]
    public ?int $branchId = null;

    public function mount(WorkspaceContextResolver $resolver): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $routeExport = request()->route()?->parameter('export');
        abort_if($routeExport !== null && (! is_string($routeExport) || DataExportType::tryFrom($routeExport) === null), 404);
        $this->downloadAttempt = PreparedDownloadAttempt::fresh();
        $this->period->fillFromQuery(request());
        $this->branchId = $resolver->resolve($user, request(), pageDestination: 'reports')->branchId;
    }

    public function downloadCsv(int $branchId, string $export, BuildDataExportsIndexAction $build, PrepareBranchCsvExportAction $csv, PreparedDownloadAttempt $attempts): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        abort_if($this->branchId !== null && $this->branchId !== $branchId, 403);
        $branch = $build->branch($user, $branchId);
        $type = DataExportType::tryFrom($export);
        abort_unless($type instanceof DataExportType, 404);
        $period = $this->period->period($branch->timezone);
        try {
            $grant = $attempts->handle($user, $this->downloadAttempt, 'csv', ['branch' => $branch->id, 'type' => $type->value, 'date_from' => $period->dateFrom, 'date_to' => $period->dateTo], fn (): string => $csv->handle($user, $branch, $type, $period->startedAt, $period->endedAt));
        } catch (RuntimeException $exception) {
            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }
            report($exception);
            $this->addError('download', __('ui.files.prepare_failed'));

            return;
        }
        $this->downloadAttempt = PreparedDownloadAttempt::fresh();
        $this->redirectRoute('restaurant.files.download', ['grant' => $grant]);
    }

    public function downloadPdf(int $branchId, string $export, BuildDataExportsIndexAction $build, BuildBranchPdfReportAction $buildPdf): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        abort_if($this->branchId !== null && $this->branchId !== $branchId, 403);
        $branch = $build->branch($user, $branchId);
        $type = DataExportType::tryFrom($export);
        abort_unless($type instanceof DataExportType, 404);
        $period = $this->period->period($branch->timezone);
        $pdf = $buildPdf->handle($user, $branch, $type, $period->startedAt, $period->endedAt);

        return response()->streamDownload(static function () use ($pdf): void {
            echo $pdf['contents'];
        }, $pdf['filename'], ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function render(BuildDataExportsIndexAction $build): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $exports = $build->handle($user, $this->branchId);
        abort_unless($exports['has_access'], 403);

        return view('livewire.exports.index', ['exports' => $exports])->title(__('reports.exports.title'));
    }
}
