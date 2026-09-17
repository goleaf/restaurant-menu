<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\ServicePoints;

use App\Actions\QrCodes\ApplyFloorQrAction;
use App\Livewire\Forms\Floor\QrOperationForm;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Enums\QrCodeStatus;
use App\Services\QrCodeSvgRenderer;
use App\Services\QrCodes\QrCodeQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QrPanel extends Component
{
    use InteractsWithFloorContext;

    #[Locked]
    public int $pointId;

    #[Locked]
    public ?int $qrId = null;

    #[Locked]
    public ?int $displayedQrVersion = null;

    #[Locked]
    public string $operation = '';

    #[Locked]
    public ?int $expectedVersion = null;

    #[Locked]
    public string $requestId = '';

    public QrOperationForm $form;

    public string $message = '';

    private QrCodeQueryService $query;

    public function boot(QrCodeQueryService $query): void
    {
        $this->query = $query;
    }

    public function mount(int $branchId, int $pointId, ?int $qrId = null): void
    {
        $this->branchId = $branchId;
        $this->pointId = $pointId;
        $this->qrId = $qrId;
        $context = $this->context();
        $this->qrId = $context['qr']?->id;
        $this->displayedQrVersion = $context['qr']?->structure_version;
    }

    public function prepareOperation(string $operation): void
    {
        $context = $this->context();
        abort_unless(in_array($operation, ['generate', 'disable', 'reissue', 'repair'], true), 422);
        if ($this->operation !== '') {
            throw ValidationException::withMessages(['operation' => __('floor.qr.finish_operation')]);
        }
        $this->operation = $operation;
        $this->qrId = $context['qr']?->id;
        $this->expectedVersion = $context['qr']?->structure_version;
        $this->requestId = (string) Str::uuid();
        $this->message = '';
        $this->resetValidation();
    }

    public function applyOperation(ApplyFloorQrAction $apply): void
    {
        $context = $this->context();
        abort_unless($this->operation !== '', 422);
        $data = $this->form->payload($this->operation);
        try {
            $qr = $apply->handle($this->actor(), $this->branch(), $context['point'], $this->operation, $this->qrId, $this->expectedVersion, $this->requestId, $data['reason'], $data['confirmation']);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors[in_array($key, ['reason', 'confirmation'], true) ? 'form.'.$key : $key] = $messages;
            }
            throw ValidationException::withMessages($errors);
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('operation', __('floor.qr.operation_incomplete'));

            return;
        }
        $this->qrId = $qr->id;
        $this->displayedQrVersion = $qr->structure_version;
        $this->clearDraft();
        $this->message = __('floor.qr.saved');
        $this->dispatch('floor-editor-saved');
        $this->dispatch('floor-qr-updated', pointId: $this->pointId);
    }

    public function cancelOperation(): void
    {
        $this->context();
        $this->clearDraft();
        $this->dispatch('floor-editor-cancelled');
    }

    public function openCurrentQr(): void
    {
        $context = $this->context();
        if ($this->operation !== '') {
            throw ValidationException::withMessages(['operation' => __('floor.qr.finish_operation')]);
        }
        $this->qrId = $context['active_id'];
        $current = $this->context();
        $this->qrId = $current['qr']?->id;
        $this->displayedQrVersion = $current['qr']?->structure_version;
        $this->resetValidation();
    }

    public function downloadQrImage(QrCodeSvgRenderer $renderer): StreamedResponse
    {
        $context = $this->context();
        $qr = $this->displayedQr($context);
        $svg = $renderer->render(route('public.qr.show', ['token' => $qr->public_token]));

        return response()->streamDownload(static function () use ($svg): void {
            echo $svg;
        }, strtolower($qr->short_code).'.svg', ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function requestPrint(): void
    {
        $qr = $this->displayedQr($this->context());
        $this->dispatch('floor-print-point', pointId: $this->pointId, qrId: $qr->id);
    }

    public function render(): View
    {
        $context = $this->context();
        $operationKey = 'floor.qr.operation.'.$this->operation;

        return view('livewire.organizations.brands.branches.service-points.qr-panel', [
            'qr' => $this->query->presentPanel($context),
            'operationLabel' => $this->operation === '' ? '' : __($operationKey),
        ]);
    }

    /** @return array{point:ServicePoint,qr:?QrCode,active_id:?int} */
    private function context(): array
    {
        $branch = $this->branch();
        Gate::forUser($this->actor())->authorize('generateQr', $branch);

        return $this->query->panel($branch, $this->pointId, $this->qrId);
    }

    private function clearDraft(): void
    {
        $this->operation = '';
        $this->expectedVersion = null;
        $this->requestId = '';
        $this->form->reset();
        $this->resetValidation();
    }

    /** @param array{point:ServicePoint,qr:?QrCode,active_id:?int} $context */
    private function displayedQr(array $context): QrCode
    {
        $qr = $context['qr'];
        if ($qr === null || $qr->status !== QrCodeStatus::Active || $qr->id !== $context['active_id'] || $qr->structure_version !== $this->displayedQrVersion) {
            throw ValidationException::withMessages(['expectedVersion' => __('floor.validation.changed')]);
        }
        return $qr;
    }
}
