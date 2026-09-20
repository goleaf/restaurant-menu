<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\ServicePoints;

use App\Actions\ServicePoints\BulkCreateServicePointsAction;
use App\Livewire\Forms\Floor\BulkPointForm;
use App\Support\Floor\FloorOptions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BulkCreate extends Component
{
    use InteractsWithFloorContext;

    public BulkPointForm $form;

    #[Locked]
    public array $preview = [];

    #[Locked]
    public string $fingerprint = '';

    #[Locked]
    public string $requestId;

    #[Locked]
    public array $baseline = [];

    #[Locked]
    public ?array $result = null;

    public string $areaSearch = '';

    private BulkCreateServicePointsAction $bulkCreateServicePointsAction;

    public function boot(BulkCreateServicePointsAction $bulkCreateServicePointsAction): void
    {
        $this->bulkCreateServicePointsAction = $bulkCreateServicePointsAction;
    }

    public function mount(int $branchId, ?int $areaId = null): void
    {
        $this->branchId = $branchId;
        Gate::forUser($this->actor())->authorize('manageServicePoints', $this->branch());
        $this->form->areaNodeId = $areaId === null ? '' : (string) $areaId;
        $this->requestId = (string) Str::uuid();
        $this->baseline = $this->form->all();
    }

    public function review(): void
    {
        $branch = $this->branch();
        Gate::forUser($this->actor())->authorize('manageServicePoints', $branch);
        $data = $this->form->payload($branch);
        try {
            $preview = $this->bulkCreateServicePointsAction->previewState($branch, $data, $this->actor());
        } catch (ValidationException $exception) {
            throw $this->scopeFieldErrors($exception);
        }
        $this->preview = $preview['rows'];
        $this->fingerprint = $preview['fingerprint'];
        $this->dispatch('menu-workspace-dirty', key: 'bulk-preview', dirty: true);
    }

    public function apply(): void
    {
        $branch = $this->branch();
        $data = $this->form->payload($branch);
        if ($this->fingerprint === '') {
            throw ValidationException::withMessages(['form.bulkPrefix' => __('floor.review_required')]);
        }
        try {
            $this->result = $this->bulkCreateServicePointsAction->handle($branch, $data, $this->actor(), $this->requestId, $this->fingerprint);
        } catch (ValidationException $exception) {
            throw $this->scopeFieldErrors($exception);
        }
        if ($this->result['created_ids'] !== []) {
            $this->dispatch('floor-bulk-created', ids: array_slice($this->result['created_ids'], 0, 100));
        }
        $this->dispatch('menu-workspace-dirty', key: 'bulk-preview', dirty: false);
        $this->saved();
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'form.')) {
            $this->preview = [];
            $this->fingerprint = '';
            $this->result = null;
            $this->requestId = (string) Str::uuid();
        }
    }

    public function render(): View
    {
        $branch = $this->branch();
        Gate::forUser($this->actor())->authorize('manageServicePoints', $branch);

        return view('livewire.organizations.brands.branches.service-points.bulk-create', [
            'types' => FloorOptions::types(), 'areas' => $this->areaOptions($this->areaSearch, $this->form->areaNodeId),
        ]);
    }

    private function scopeFieldErrors(ValidationException $exception): ValidationException
    {
        $fields = $this->form->all();
        $errors = [];

        foreach ($exception->errors() as $field => $messages) {
            $key = array_key_exists($field, $fields) ? $this->form->getPropertyName().'.'.$field : $field;
            $errors[$key] = $messages;
        }

        return ValidationException::withMessages($errors);
    }
}
