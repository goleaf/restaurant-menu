<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Availability;

use App\Actions\Branches\SaveBranchScheduleExceptionsAction;
use App\Actions\Branches\UpdateBranchOpeningHoursAction;
use App\Actions\Branches\UpdateBranchTemporaryClosureAction;
use App\Actions\Menus\SaveMenuAvailabilityScheduleAction;
use App\Actions\Menus\SetMenuItemsRestrictionAction;
use App\Livewire\Forms\Availability\EvaluationForm;
use App\Livewire\Forms\Availability\ExceptionsForm;
use App\Livewire\Forms\Availability\PauseForm;
use App\Livewire\Forms\Availability\RestrictionForm;
use App\Livewire\Forms\Availability\StopListFilterForm;
use App\Livewire\Forms\Availability\WeeklyScheduleForm;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use App\Services\Availability\AvailabilityEvaluator;
use App\Services\Availability\AvailabilityWorkspaceQuery;
use App\Support\Availability\AvailabilityDependencyFingerprint;
use App\Support\Validation\Availability\BranchLocalDateTime;
use Carbon\CarbonImmutable;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

final class Index extends Component
{
    #[Locked]
    public int $organizationId;

    #[Locked]
    public int $brandId;

    #[Locked]
    public int $branchId;

    #[Url(history: true, except: 'now')]
    public mixed $section = 'now';

    #[Url(as: 'menu', history: true)]
    public mixed $menuId = '';

    public mixed $menuSearch = '';

    public StopListFilterForm $filters;

    public RestrictionForm $restriction;

    public PauseForm $pause;

    public WeeklyScheduleForm $weekly;

    public ExceptionsForm $exceptions;

    public EvaluationForm $evaluation;

    #[Locked]
    public ?string $evaluationAt = null;

    /** @var array<string,mixed> */
    #[Locked]
    public array $dayCopyPreview = [];

    #[Locked]
    public string $dayCopyHash = '';

    #[Locked]
    public int $expectedVersion = 0;

    #[Locked]
    public ?int $targetMenuId = null;

    #[Url(as: 'item', history: true)]
    public mixed $itemId = '';

    public mixed $selectedItems = [];

    #[Locked]
    public string $editor = '';

    /** @var list<array{id:int,version:int}> */
    #[Locked]
    public array $targets = [];

    /** @var list<array{id:int,version:int}> */
    #[Locked]
    public array $renderedTargets = [];

    /** @var array<string,mixed> */
    #[Locked]
    public array $preview = [];

    #[Locked]
    public string $previewHash = '';

    #[Locked]
    public string $requestId = '';

    #[Locked]
    public string $expectedTimezone = '';

    /** @var array<int,string> */
    #[Locked]
    public array $expectedContexts = [];

    #[Locked]
    public ?string $expectedContext = null;

    private AvailabilityWorkspaceQuery $queries;

    private AvailabilityEvaluator $evaluator;

    private Application $application;

    public function boot(AvailabilityWorkspaceQuery $queries, AvailabilityEvaluator $evaluator, Application $application): void
    {
        $this->queries = $queries;
        $this->evaluator = $evaluator;
        $this->application = $application;
    }

    public function exception(Throwable $e, Closure $stopPropagation): void
    {
        if ($e instanceof ValidationException) {
            $this->dispatch('availability-validation-failed');
        }
    }

    public function mount(Organization $organization, Brand $brand, Branch $branch): void
    {
        $this->organizationId = $organization->id;
        $this->brandId = $brand->id;
        $this->branchId = $branch->id;
        $context = $this->authorizedContext();
        $sections = $this->allowedSections($context['branch']);
        if (! is_string($this->section) || ! in_array($this->section, $sections, true)) {
            $this->section = $sections[0];
        }
        if (filled($this->itemId)) {
            $validated = Validator::make(['itemId' => $this->itemId], ['itemId' => ['required', 'numeric', 'integer', 'min:1']])->validate();
            $this->section = 'stoplist';
            $this->openItem((int) $validated['itemId']);
        }
    }

    public function evaluate(): void
    {
        $branch = $this->authorizedContext()['branch'];
        $this->evaluationAt = $this->evaluation->validatedInstant($branch->timezone);
        $this->preview = [];
    }

    public function previewDayCopy(): void
    {
        $this->authorizeScheduleEditor();
        $copy = $this->weekly->validatedCopy();
        $this->dayCopyPreview = $copy;
        $this->dayCopyHash = hash('sha256', json_encode($copy, JSON_THROW_ON_ERROR));
        $this->dispatch('availability-draft-dirty');
    }

    public function copyDays(): void
    {
        $this->authorizeScheduleEditor();
        $copy = $this->weekly->validatedCopy();
        if ($this->dayCopyPreview === [] || ! hash_equals($this->dayCopyHash, hash('sha256', json_encode($copy, JSON_THROW_ON_ERROR)))) {
            throw ValidationException::withMessages(['weekly.copyTo' => __('availability.preview_required')]);
        }
        $this->weekly->copyDays($copy);
        $this->preview = [];
        $this->dayCopyPreview = [];
        $this->dayCopyHash = '';
        $this->dispatch('availability-draft-dirty');
    }

    public function openPause(): void
    {
        $branch = $this->authorizedContext()['branch'];
        Gate::forUser($this->actor())->authorize('manageSettings', $branch);
        $this->startTemporalEditor($branch, 'pause', (int) $branch->pause_version);
        $this->pause->mode = $branch->is_temporarily_closed ? 'resume' : 'indefinite';
    }

    public function previewPause(): void
    {
        $branch = $this->temporalBranch('pause', 'pause_version');
        $draft = $this->pause->validatedDraft($this->expectedTimezone);
        $at = $this->evaluationInstant();
        $projected = clone $branch;
        $until = $draft['until'] === null ? null : BranchLocalDateTime::parse($draft['until'], $this->expectedTimezone)->utc();
        if ($draft['duration'] !== null) {
            $until = CarbonImmutable::now('UTC')->addMinutes($draft['duration']);
        }
        $projected->forceFill(['is_temporarily_closed' => $draft['closed'], 'temporary_closed_reason' => $draft['closed'] ? $draft['reason'] : null,
            'temporary_closed_until' => $draft['closed'] ? $until : null]);
        $projected->syncOriginalAttribute('temporary_closed_until');
        $this->expectedContext = AvailabilityDependencyFingerprint::branch($branch);
        $this->prepareTemporalPreview($draft, $branch->name, $this->evaluator->branchRestrictions($branch, $at)->toArray(), $this->evaluator->branchRestrictions($projected, $at)->toArray());
    }

    public function applyPause(UpdateBranchTemporaryClosureAction $action): void
    {
        $branch = $this->temporalBranch('pause', 'pause_version', false);
        $draft = $this->pause->validatedDraft($this->expectedTimezone, false);
        $this->assertPreview($draft, 'pause.mode');
        $this->attemptTemporal(fn () => $action->handle($this->actor(), $branch, $draft['closed'], $draft['reason'], $draft['until'], $this->expectedVersion, $this->expectedTimezone, $this->requestId, $draft['duration'], $this->expectedContext), 'pause');
        $this->completed();
    }

    public function openHours(): void
    {
        $branch = $this->authorizedContext()['branch'];
        Gate::forUser($this->actor())->authorize('manageSettings', $branch);
        $this->startTemporalEditor($branch, 'hours', (int) $branch->opening_hours_version);
        $schedule = $this->queries->branchDays($branch);
        $this->weekly->populate($schedule['days'], $schedule['configured'] ? 'weekly' : 'unrestricted');
    }

    public function openMenuSchedule(): void
    {
        $branch = $this->authorizedContext()['branch'];
        Gate::forUser($this->actor())->authorize('manageMenu', $branch);
        $id = $this->selectedMenuId(true);
        $menu = $id === null ? null : $this->queries->findMenu($branch, $id);
        if ($menu === null) {
            throw ValidationException::withMessages(['menuId' => __('availability.choose_menu')]);
        }
        $this->startTemporalEditor($branch, 'menu', (int) $menu->schedule_version);
        $this->targetMenuId = $menu->id;
        $this->weekly->populate($this->queries->menuDays($menu), $menu->schedule_is_closed ? 'closed' : ($menu->availabilitySchedules->isEmpty() ? 'unrestricted' : 'weekly'));
    }

    public function addInterval(int $dayIndex): void
    {
        $this->authorizeScheduleEditor();
        $this->weekly->addInterval($dayIndex);
        $this->preview = [];
        $this->dispatch('availability-draft-dirty');
    }

    public function removeInterval(int $dayIndex, int $intervalIndex): void
    {
        $this->authorizeScheduleEditor();
        $this->weekly->removeInterval($dayIndex, $intervalIndex);
        $this->preview = [];
        $this->dispatch('availability-draft-dirty');
    }

    public function previewSchedule(): void
    {
        $branch = $this->authorizeScheduleEditor();
        $draft = $this->weekly->validatedOperation();
        $at = $this->evaluationInstant();
        if ($this->editor === 'menu') {
            $menu = $this->queries->menu($branch, $this->targetMenuId ?? 0);
            if ((int) $menu->schedule_version !== $this->expectedVersion) {
                throw ValidationException::withMessages(['weekly.mode' => __('availability.conflict')]);
            }
            $this->expectedContext = AvailabilityDependencyFingerprint::menu($menu);
            $this->prepareTemporalPreview($draft, $menu->name, $this->evaluator->menu($menu, $at)->toArray(),
                $this->evaluator->menu($this->queries->projectedMenu($menu, $draft['intervals'], $draft['mode'] === 'closed'), $at)->toArray());
        } else {
            $this->expectedContext = AvailabilityDependencyFingerprint::branch($branch);
            $this->prepareTemporalPreview($draft, $branch->name, $this->evaluator->branchRestrictions($branch, $at)->toArray(),
                $this->evaluator->branchRestrictions($this->queries->projectedHours($branch, $draft['days'], $draft['mode'] !== 'unrestricted'), $at)->toArray());
        }
    }

    public function applySchedule(UpdateBranchOpeningHoursAction $hours, SaveMenuAvailabilityScheduleAction $menus): void
    {
        $branch = $this->authorizeScheduleEditor(false);
        $draft = $this->weekly->validatedOperation();
        $this->assertPreview($draft, 'weekly.mode');
        if ($this->editor === 'menu') {
            $menu = $this->queries->menu($branch, $this->targetMenuId ?? 0);
            $this->attemptTemporal(fn () => $menus->handle($this->actor(), $branch, $menu, $draft['intervals'], $draft['mode'] === 'closed', $this->expectedVersion, $this->expectedTimezone, $this->requestId, $this->expectedContext), 'weekly');
        } else {
            $this->attemptTemporal(fn () => $hours->handle($this->actor(), $branch, $draft['days'], $draft['mode'] !== 'unrestricted', $this->expectedVersion, $this->expectedTimezone, $this->requestId, $this->expectedContext), 'weekly');
        }
        $this->completed();
    }

    public function openExceptions(): void
    {
        $branch = $this->authorizedContext()['branch'];
        Gate::forUser($this->actor())->authorize('manageSettings', $branch);
        $this->startTemporalEditor($branch, 'exceptions', (int) $branch->opening_hours_version);
        $this->exceptions->exceptions = $this->queries->exceptions($branch);
    }

    public function addException(): void
    {
        $this->temporalBranch('exceptions', 'opening_hours_version');
        if (is_array($this->exceptions->exceptions) && count($this->exceptions->exceptions) < 366) {
            $this->exceptions->exceptions[] = ['local_date' => '', 'is_closed' => true, 'intervals' => [['opens_at' => '10:00', 'closes_at' => '22:00']]];
            $this->dispatch('availability-draft-dirty');
        }
    }

    public function removeException(int $index): void
    {
        $this->temporalBranch('exceptions', 'opening_hours_version');
        if (is_array($this->exceptions->exceptions)) {
            unset($this->exceptions->exceptions[$index]);
            $this->exceptions->exceptions = array_values($this->exceptions->exceptions);
            $this->preview = [];
            $this->dispatch('availability-draft-dirty');
        }
    }

    public function addExceptionInterval(int $index): void
    {
        $this->temporalBranch('exceptions', 'opening_hours_version');
        if (is_array($this->exceptions->exceptions) && is_array($this->exceptions->exceptions[$index]['intervals'] ?? null) && count($this->exceptions->exceptions[$index]['intervals']) < 4) {
            $this->exceptions->exceptions[$index]['intervals'][] = ['opens_at' => '10:00', 'closes_at' => '22:00'];
            $this->preview = [];
            $this->dispatch('availability-draft-dirty');
        }
    }

    public function removeExceptionInterval(int $index, int $intervalIndex): void
    {
        $this->temporalBranch('exceptions', 'opening_hours_version');
        if (is_array($this->exceptions->exceptions) && is_array($this->exceptions->exceptions[$index]['intervals'] ?? null)) {
            unset($this->exceptions->exceptions[$index]['intervals'][$intervalIndex]);
            $this->exceptions->exceptions[$index]['intervals'] = array_values($this->exceptions->exceptions[$index]['intervals']);
            $this->preview = [];
            $this->dispatch('availability-draft-dirty');
        }
    }

    public function previewExceptions(): void
    {
        $branch = $this->temporalBranch('exceptions', 'opening_hours_version');
        $draft = $this->exceptions->validatedDraft();
        $this->expectedContext = AvailabilityDependencyFingerprint::branch($branch);
        $this->preview = ['operation' => 'exceptions', 'before' => $this->queries->exceptions($branch), 'after' => $draft];
        $this->previewHash = $this->draftHash($draft);
        $this->dispatch('availability-preview-ready');
    }

    public function applyExceptions(SaveBranchScheduleExceptionsAction $action): void
    {
        $branch = $this->temporalBranch('exceptions', 'opening_hours_version', false);
        $draft = $this->exceptions->validatedDraft();
        $this->assertPreview($draft, 'exceptions.exceptions');
        $this->attemptTemporal(fn () => $action->handle($this->actor(), $branch, $draft, $this->expectedVersion, $this->expectedTimezone, $this->requestId, $this->expectedContext), 'exceptions');
        $this->completed();
    }

    public function selectSection(mixed $section): void
    {
        $context = $this->authorizedContext();
        abort_unless(is_string($section) && in_array($section, $this->allowedSections($context['branch']), true), 403);
        $this->discardDraft();
        $this->section = $section;
    }

    public function updatedSection(): void
    {
        $this->selectSection($this->section);
    }

    public function updatedFilters(): void
    {
        $this->filters->page = 1;
        $this->selectedItems = [];
    }

    public function updatedMenuId(): void
    {
        $this->authorizedContext();
        $this->filters->page = 1;
        $this->selectedItems = [];
    }

    public function previousPage(string $pageName = 'page'): void
    {
        $this->setPage((int) $this->filters->page - 1);
    }

    public function nextPage(string $pageName = 'page'): void
    {
        $this->setPage((int) $this->filters->page + 1);
    }

    public function gotoPage(int $page, string $pageName = 'page'): void
    {
        $this->setPage($page);
    }

    public function setPage(int $page): void
    {
        $this->authorizedContext();
        $this->filters->page = max(1, min(10000, $page));
        $this->selectedItems = [];
    }

    public function openItem(int $id): void
    {
        $branch = $this->authorizedContext()['branch'];
        Gate::forUser($this->actor())->authorize('changeMenuAvailability', $branch);
        $item = $this->queries->item($branch, $id);
        $this->startRestriction($branch, [['id' => $item->id, 'version' => (int) $item->availability_version]]);
    }

    public function openBulk(): void
    {
        $branch = $this->authorizedContext()['branch'];
        Gate::forUser($this->actor())->authorize('changeMenuAvailability', $branch);
        $data = Validator::make(['selectedItems' => $this->selectedItems], [
            'selectedItems' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'selectedItems.*' => ['required', 'numeric', 'integer', 'distinct', Rule::in(array_column($this->renderedTargets, 'id'))],
        ])->validate();
        $selected = array_map(intval(...), $data['selectedItems']);
        $targets = array_values(array_filter($this->renderedTargets, fn (array $target): bool => in_array($target['id'], $selected, true)));
        $this->startRestriction($branch, $targets);
    }

    public function previewRestriction(): void
    {
        $branch = $this->authorizedContext()['branch'];
        Gate::forUser($this->actor())->authorize('changeMenuAvailability', $branch);
        abort_unless($this->editor === 'restriction' && $this->targets !== [], 422);
        $draft = $this->restriction->validatedDraft();
        $at = $this->evaluationInstant();
        if ($draft['until'] !== null) {
            Validator::make(['restriction' => ['untilDate' => $draft['until']]], ['restriction.untilDate' => [new BranchLocalDateTime($this->expectedTimezone, CarbonImmutable::now('UTC'))]])->validate();
        }
        $until = $draft['until'] === null ? null : BranchLocalDateTime::parse($draft['until'], $this->expectedTimezone);
        $rows = [];
        $this->expectedContexts = [];
        $items = $this->queries->selectedItems($branch, array_column($this->targets, 'id'));
        foreach ($this->targets as $target) {
            $item = $items->get($target['id']);
            abort_unless($item !== null, 404);
            if ((int) $item->availability_version !== $target['version'] || $branch->timezone !== $this->expectedTimezone) {
                throw ValidationException::withMessages(['restriction.operation' => __('availability.conflict')]);
            }
            $this->expectedContexts[$target['id']] = AvailabilityDependencyFingerprint::item($item);
        }
        $before = $this->evaluator->items($items, $at);
        $after = $this->evaluator->projectItems($items, $at, $draft['operation'], $until);
        foreach ($this->targets as $target) {
            $rows[] = ['name' => $items[$target['id']]->name, 'before' => $this->queries->present($before[$target['id']]->toArray(), $this->expectedTimezone),
                'after' => $this->queries->present($after[$target['id']]->toArray(), $this->expectedTimezone)];
        }
        $this->preview = ['operation' => $draft['operation'], 'rows' => $rows, 'evaluated_at' => $at->toIso8601String()];
        $this->previewHash = $this->draftHash($draft);
        $this->dispatch('availability-preview-ready');
    }

    public function applyRestriction(SetMenuItemsRestrictionAction $action): void
    {
        $branch = $this->authorizedContext()['branch'];
        Gate::forUser($this->actor())->authorize('changeMenuAvailability', $branch);
        $draft = $this->restriction->validatedDraft();
        if ($this->editor !== 'restriction' || $this->preview === [] || ! hash_equals($this->previewHash, $this->draftHash($draft))) {
            throw ValidationException::withMessages(['restriction.operation' => __('availability.preview_required')]);
        }
        try {
            $action->handle($this->actor(), $branch, $this->targets, $draft['operation'], $draft['until'], $this->expectedTimezone, $this->requestId, $draft['reason'], $this->expectedContexts);
        } catch (ValidationException $exception) {
            $messages = [];
            foreach ($exception->errors() as $field => $errors) {
                $messages[match ($field) {
                    'untilLocal' => 'restriction.untilDate', 'reason' => 'restriction.reason', default => 'restriction.operation',
                }] = $errors;
            }
            throw ValidationException::withMessages($messages);
        }
        $this->discardDraft();
        $this->selectedItems = [];
        Flux::toast(text: __('availability.applied'), variant: 'success');
    }

    public function discardDraft(): void
    {
        $this->restriction->reset();
        $this->pause->reset();
        $this->weekly->reset();
        $this->exceptions->reset();
        $this->targetMenuId = null;
        $this->dayCopyPreview = [];
        $this->dayCopyHash = '';
        $this->editor = '';
        $this->targets = [];
        $this->preview = [];
        $this->previewHash = '';
        $this->requestId = '';
        $this->expectedContext = null;
        $this->expectedContexts = [];
        $this->resetValidation();
        $this->dispatch('availability-draft-cleared');
    }

    public function render(): View
    {
        $context = $this->authorizedContext();
        $branch = $context['branch'];
        $sections = $this->allowedSections($branch);
        abort_unless(is_string($this->section) && in_array($this->section, $sections, true), 403);
        $at = $this->evaluationInstant();
        $items = null;
        $menuId = $this->selectedMenuId();
        $menu = $menuId === null || $this->section === 'now' ? null : $this->queries->findMenu($branch, $menuId);
        if ($this->section === 'stoplist') {
            $filterValues = $this->filtersForRender();
            $items = $filterValues === null ? new LengthAwarePaginator([], 0, 20) : $this->queries->items($branch, $filterValues, $menuId, $at);
            $this->renderedTargets = $items->getCollection()->map(fn (array $row): array => ['id' => $row['id'], 'version' => $row['version']])->all();
        }
        $menuSearch = $this->menuSearchForRender();
        $sectionLinks = [];
        foreach ($sections as $section) {
            $labelKey = 'availability.section.'.$section;
            $sectionLinks[] = ['key' => $section, 'label' => __($labelKey), 'url' => route('organizations.brands.branches.availability.index', [$context['organization'], $context['brand'], $branch, 'section' => $section])];
        }
        $detail = $this->editor === 'restriction' && count($this->targets) === 1 ? $this->queries->itemRow($this->queries->item($branch, $this->targets[0]['id']), $at, $this->actor()) : null;

        return view('livewire.organizations.brands.branches.availability.index', [
            'contextDescription' => $context['organization']->name.' / '.$context['brand']->name.' / '.$branch->name,
            'timezoneLabel' => __('availability.timezone', ['timezone' => $branch->timezone]),
            'sections' => $sectionLinks, 'items' => $items, 'menuOptions' => $this->section === 'now' ? [] : $this->queries->menuOptions($branch, (string) $menuSearch, $menu),
            'editorMenuName' => $this->targetMenuId === null ? null : ($this->targetMenuId === $menu?->id ? $menu->name : $this->queries->menu($branch, $this->targetMenuId)->name), 'weeklyDays' => $this->weekly->displayDays(), 'exceptionRows' => $this->exceptions->displayRows(), 'detail' => $detail, 'now' => $this->section === 'now' ? $this->queries->present($this->evaluator->branch($branch, $at)->toArray(), $branch->timezone) : null, 'locale' => $this->application->getLocale(), 'evaluationLabel' => $this->queries->present(['evaluated_at' => $this->evaluationAt], $branch->timezone)['evaluated_at'],
            'canManageSettings' => Gate::forUser($this->actor())->allows('manageSettings', $branch),
            'canManageMenu' => Gate::forUser($this->actor())->allows('manageMenu', $branch),
        ])->title(__('availability.title'));
    }

    /** @return array{organization:Organization,brand:Brand,branch:Branch} */
    private function authorizedContext(): array
    {
        $context = $this->queries->context($this->organizationId, $this->brandId, $this->branchId);
        Gate::forUser($this->actor())->authorize('viewAvailabilityCenter', $context['branch']);
        abort_if($this->allowedSections($context['branch']) === [], 403);

        return $context;
    }

    /** @return list<string> */
    private function allowedSections(Branch $branch): array
    {
        $gate = Gate::forUser($this->actor());
        $sections = [];
        if ($gate->allows('manageSettings', $branch) || $gate->allows('manageMenu', $branch)) {
            $sections = ['now', 'schedules'];
        }
        if ($gate->allows('changeMenuAvailability', $branch)) {
            $sections[] = 'stoplist';
        }

        return $sections;
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 401);

        return $actor->fresh() ?? abort(401);
    }

    private function selectedMenuId(bool $validate = false): ?int
    {
        if (! $validate) {
            return (is_string($this->menuId) || is_int($this->menuId)) && preg_match('/^[1-9][0-9]{0,17}$/D', (string) $this->menuId) === 1 ? (int) $this->menuId : null;
        }
        $data = Validator::make(['menuId' => $this->menuId], ['menuId' => ['nullable', 'numeric', 'integer', 'min:1']])->validate();

        return blank($data['menuId']) ? null : (int) $data['menuId'];
    }

    /** @param list<array{id:int,version:int}> $targets */
    private function startRestriction(Branch $branch, array $targets): void
    {
        $this->discardDraft();
        $this->editor = 'restriction';
        $this->targets = $targets;
        $this->requestId = (string) Str::uuid();
        $this->expectedTimezone = $branch->timezone;
        $this->dispatch('availability-editor-opened');
    }

    /** @return array{search:string,state:string,page:int}|null */
    private function filtersForRender(): ?array
    {
        $this->resetValidation(['filters.search', 'filters.state', 'filters.page']);
        try {
            return $this->filters->validatedFilters();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return null;
        }
    }

    private function menuSearchForRender(): string
    {
        $this->resetValidation('menuSearch');
        try {
            $values = Validator::make(['menuSearch' => $this->menuSearch], ['menuSearch' => ['nullable', 'string', 'max:100']], [], ['menuSearch' => __('availability.search_menus')])->validate();

            return (string) $values['menuSearch'];
        } catch (ValidationException $exception) {
            $this->addError('menuSearch', $exception->errors()['menuSearch'][0]);

            return '';
        }
    }

    private function evaluationInstant(): CarbonImmutable
    {
        return $this->evaluationAt === null ? CarbonImmutable::now('UTC') : CarbonImmutable::parse($this->evaluationAt);
    }

    private function startTemporalEditor(Branch $branch, string $editor, int $version): void
    {
        $this->discardDraft();
        $this->editor = $editor;
        $this->expectedVersion = $version;
        $this->expectedTimezone = $branch->timezone;
        $this->requestId = (string) Str::uuid();
        $this->dispatch('availability-editor-opened');
    }

    private function temporalBranch(string $editor, string $version, bool $checkVersion = true): Branch
    {
        $branch = $this->authorizedContext()['branch'];
        Gate::forUser($this->actor())->authorize('manageSettings', $branch);
        abort_unless($this->editor === $editor, 422);
        if ($checkVersion && ((int) $branch->getAttribute($version) !== $this->expectedVersion || $branch->timezone !== $this->expectedTimezone)) {
            throw ValidationException::withMessages([match ($editor) {
                'pause' => 'pause.mode', 'exceptions' => 'exceptions.exceptions', default => 'weekly.mode'
            } => __('availability.conflict')]);
        }

        return $branch;
    }

    private function authorizeScheduleEditor(bool $checkVersion = true): Branch
    {
        if ($this->editor === 'hours') {
            return $this->temporalBranch('hours', 'opening_hours_version', $checkVersion);
        }
        $branch = $this->authorizedContext()['branch'];
        Gate::forUser($this->actor())->authorize('manageMenu', $branch);
        abort_unless($this->editor === 'menu', 422);

        return $branch;
    }

    /** @param array<string,mixed> $draft @param array<string,mixed> $before @param array<string,mixed> $after */
    private function prepareTemporalPreview(array $draft, string $name, array $before, array $after): void
    {
        $this->preview = ['operation' => $this->editor, 'rows' => [['name' => $name, 'before' => $this->queries->present($before, $this->expectedTimezone), 'after' => $this->queries->present($after, $this->expectedTimezone)]]];
        $this->previewHash = $this->draftHash($draft);
        $this->dispatch('availability-preview-ready');
    }

    /** @param array<array-key,mixed> $draft */
    private function assertPreview(array $draft, string $field): void
    {
        if ($this->preview === [] || ! hash_equals($this->previewHash, $this->draftHash($draft))) {
            throw ValidationException::withMessages([$field => __('availability.preview_required')]);
        }
    }

    private function attemptTemporal(callable $operation, string $form): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            $messages = [];
            foreach ($exception->errors() as $field => $errors) {
                $target = match ($form) {
                    'pause' => match ($field) {
                        'untilLocal' => 'pause.untilDate', 'reason' => 'pause.reason', 'durationMinutes' => 'pause.durationMinutes', default => 'pause.mode'
                    },
                    'exceptions' => str_starts_with($field, 'exceptions') ? 'exceptions.'.$field : 'exceptions.exceptions',
                    default => str_starts_with($field, 'openingHours') ? 'weekly.'.$field : 'weekly.mode',
                };
                $messages[$target] = $errors;
            }
            throw ValidationException::withMessages($messages);
        }
    }

    private function completed(): void
    {
        $this->discardDraft();
        Flux::toast(text: __('availability.applied'), variant: 'success');
    }

    /** @param array<array-key,mixed> $draft */
    private function draftHash(array $draft): string
    {
        return hash('sha256', json_encode([$draft, $this->evaluationAt, $this->editor, $this->expectedVersion, $this->targetMenuId, $this->targets, $this->requestId, $this->expectedTimezone, $this->expectedContext, $this->expectedContexts], JSON_THROW_ON_ERROR));
    }
}
