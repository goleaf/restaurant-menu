<?php

declare(strict_types=1);

namespace App\Livewire\Restaurant;

use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Actions\Dashboard\SaveDashboardOrderingAction;
use App\Livewire\Forms\BranchOrderingForm;
use App\Models\User;
use App\Services\Navigation\WorkspaceContextResolver;
use App\Support\Reports\BranchReportPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class Dashboard extends Component
{
    #[Locked]
    public bool $aggregate = false;

    #[Locked]
    public mixed $selectedBranchId = '';

    #[Url(as: 'period', history: true, except: 'today')]
    public mixed $reportRange = 'today';

    #[Locked]
    public mixed $period = 'today';

    #[Locked]
    public mixed $dateFrom = '';

    #[Locked]
    public mixed $dateTo = '';

    public mixed $periodDraft = 'today';

    public mixed $dateFromDraft = '';

    public mixed $dateToDraft = '';

    public BranchOrderingForm $closure;

    public bool $canAccessRestaurantDashboard = false;

    public string $successMessage = '';

    public string $errorMessage = '';

    #[Locked]
    public ?int $renderedBranchId = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $originalClosure = [];

    public function mount(WorkspaceContextResolver $resolver): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $context = $resolver->resolve($user, request(), pageDestination: 'overview');
        $this->aggregate = $context->mode === 'aggregate';
        $this->selectedBranchId = $context->branchId === null ? '' : (string) $context->branchId;
        $this->syncPeriodDraft();
    }

    public function updatedReportRange(): void
    {
        $this->syncPeriodDraft();
    }

    private function syncPeriodDraft(): void
    {
        $parts = is_string($this->reportRange) ? explode(':', $this->reportRange) : [];
        $this->period = count($parts) === 3 && $parts[0] === 'custom' ? 'custom' : $this->reportRange;
        $this->dateFrom = count($parts) === 3 ? $parts[1] : '';
        $this->dateTo = count($parts) === 3 ? $parts[2] : '';
        $this->periodDraft = $this->period;
        $this->dateFromDraft = $this->dateFrom;
        $this->dateToDraft = $this->dateTo;
    }

    public function applyPeriod(BuildRestaurantDashboardAction $build): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';
        $this->resetValidation();
        $this->validate([
            'periodDraft' => ['required', 'string', 'in:today,yesterday,last7,custom'],
            'dateFromDraft' => ['nullable', 'string', 'max:10'], 'dateToDraft' => ['nullable', 'string', 'max:10'],
        ]);
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $context = $build->context($user, $this->selectedBranchId, $this->aggregate);
        try {
            BranchReportPeriod::fromSelection($context['branches'], $this->periodDraft, $this->dateFromDraft ?: null, $this->dateToDraft ?: null);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError(strtr($field, ['date_from' => 'dateFromDraft', 'date_to' => 'dateToDraft', 'period' => 'periodDraft']), $messages[0]);
                $this->dispatch('dashboard-validation-failed');
            }

            return;
        }
        $this->period = $this->periodDraft;
        $this->dateFrom = $this->period === 'custom' ? $this->dateFromDraft : '';
        $this->dateTo = $this->period === 'custom' ? $this->dateToDraft : '';
        $this->reportRange = $this->period === 'custom' ? 'custom:'.$this->dateFrom.':'.$this->dateTo : $this->period;
    }

    public function refreshDashboard(): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    public function refreshOperations(): void {}

    public function discardOrdering(): void
    {
        $this->closure->fill([
            'temporarilyClosed' => $this->originalClosure['temporarilyClosed'] ?? false,
            'temporaryClosedReason' => $this->originalClosure['temporaryClosedReason'] ?? '',
            'temporaryClosedUntil' => $this->originalClosure['temporaryClosedUntil'] ?? '',
        ]);
        $this->resetValidation();
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    public function saveOrdering(SaveDashboardOrderingAction $save): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $branchId = BuildRestaurantDashboardAction::validatedBranchId($this->selectedBranchId);
        if ($branchId === null) {
            $this->addError('selectedBranchId', __('dashboard.control.choose_branch'));

            return;
        }
        try {
            $data = $this->closure->validatedClosure();
        } catch (ValidationException $exception) {
            $this->dispatch('dashboard-validation-failed');
            throw $exception;
        }
        try {
            $save->handle($user, $branchId, $data['closed'], $data['reason'], $data['until']);
            $this->originalClosure = [];
            $this->successMessage = __('dashboard.control.ordering_saved');
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->errorMessage = __('dashboard.control.save_failed');
        }
    }

    public function render(BuildRestaurantDashboardAction $build): View
    {
        $dashboard = null;
        $user = Auth::user();
        if ($user instanceof User) {
            try {
                Validator::make($this->only(['period', 'dateFrom', 'dateTo']), [
                    'period' => ['required', 'string', 'in:today,yesterday,last7,custom'],
                    'dateFrom' => ['nullable', 'string', 'max:10'],
                    'dateTo' => ['nullable', 'string', 'max:10'],
                ])->validate();
                $payload = $build->handle($user, $this->selectedBranchId, $this->period, $this->dateFrom ?: null, $this->dateTo ?: null, $this->aggregate);
                $dashboard = $payload['dashboard'];
                $this->canAccessRestaurantDashboard = $payload['has_access'];
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    $this->addError(strtr($field, ['date_from' => 'dateFrom', 'date_to' => 'dateTo']), $messages[0]);
                }
                $this->errorMessage = __('dashboard.control.invalid_context');
                if (array_key_exists('selectedBranchId', $exception->errors())) {
                    abort(403);
                }
                $fallback = $build->handle($user, $this->selectedBranchId, aggregate: $this->aggregate);
                $dashboard = $fallback['dashboard'];
                $this->canAccessRestaurantDashboard = $fallback['has_access'];
                if ($dashboard !== null) {
                    $dashboard['report']['unavailable'] = true;
                    $dashboard['report']['metrics'] = [];
                    $dashboard['report']['popular_items'] = [];
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->errorMessage = __('dashboard.control.load_failed');
                $this->clearContext();
            }
        }
        if ($dashboard === null) {
            $this->clearContext();
        } else {
            $selected = $dashboard['selected_branch'];
            $this->selectedBranchId = $selected === null ? '' : (string) $selected['id'];
            if ($this->renderedBranchId !== ($selected['id'] ?? null) || ! $this->orderingIsDirty()) {
                $ordering = $dashboard['ordering'];
                $this->originalClosure = [
                    'temporarilyClosed' => $ordering['is_temporarily_closed'] ?? false,
                    'temporaryClosedReason' => $ordering['reason'] ?? '',
                    'temporaryClosedUntil' => $ordering['until_input'] ?? '',
                ];
                $this->closure->fill([
                    'temporarilyClosed' => $this->originalClosure['temporarilyClosed'],
                    'temporaryClosedReason' => $this->originalClosure['temporaryClosedReason'],
                    'temporaryClosedUntil' => $this->originalClosure['temporaryClosedUntil'],
                ]);
            }
            $this->renderedBranchId = $selected['id'] ?? null;

        }

        return view('livewire.restaurant.dashboard', ['dashboard' => $dashboard]);
    }

    private function orderingIsDirty(): bool
    {
        return $this->originalClosure !== [] && $this->closure->all() !== $this->originalClosure;
    }

    private function clearContext(): void
    {
        $this->canAccessRestaurantDashboard = false;
        $this->renderedBranchId = null;
        $this->originalClosure = [];
        $this->closure->reset();
        $this->successMessage = '';
    }
}
