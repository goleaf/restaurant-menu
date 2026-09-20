@pushOnce('page-scripts', 'preparation-styles')
    @vite('resources/scss/preparation.scss')
@endPushOnce

@pushOnce('page-module-status', 'departments-status')
    <x-page-module-status module="departments" :heading="__('frontend.timers_loading')" :description="__('frontend.timers_loading_help')" />
@endPushOnce

<section x-data="kitchenTimers" data-page="{{ $dataPage }}" data-preparation-workspace
    data-compact="{{ $compact ? 'true' : 'false' }}"
    wire:poll.visible.3s="refreshDepartment" class="prep-workspace"
    wire:loading.attr="aria-busy" aria-busy="false">
    <p role="status" aria-atomic="true" class="sr-only">{{ $updateAnnouncement }}</p>

    <x-ui.page-header :title="$pageTitle" :description="$pageSubtitle" :context="$branchName">
        <x-slot:actions>
            <flux:switch wire:model.live="compact" data-preparation-compact :label="__('preparation.compact')" />
        </x-slot:actions>
    </x-ui.page-header>

    <div class="prep-context">
        <div class="prep-context__department">
            <flux:select variant="listbox" searchable wire:model.live="selectedDepartmentId"
                data-preparation-department :label="__('ui.departments.dashboard.department')"
                wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled">
                <x-slot:search><flux:select.search :placeholder="__('ui.departments.dashboard.department')" :aria-label="__('ui.departments.dashboard.department')" /></x-slot:search>
                @if (count($departments) > 1)
                    <flux:select.option value="all">{{ __('preparation.all_departments') }}</flux:select.option>
                @endif
                @forelse ($departments as $department)
                    <flux:select.option wire:key="preparation-department-{{ $department['id'] }}" value="{{ $department['id'] }}">
                        {{ $department['name'] }}@if (! $department['is_active']) · {{ __('ui.status.inactive') }}@endif
                    </flux:select.option>
                @empty
                @endforelse
            </flux:select>
        </div>
        <div class="prep-context__checked">
            <p>{{ __('preparation.scope', ['department' => $selectedDepartmentName]) }}</p>
            <p>{{ __('preparation.checked', ['time' => $refreshedAt]) }}</p>
            <p>{{ __('ui.departments.dashboard.sort') }}: {{ $sortLabel }}</p>
        </div>
        <flux:button type="button" icon="arrow-path" data-preparation-refresh x-on:click="refreshContext"
            x-bind:disabled="!online || revalidating || pendingRequests > 0" class="prep-control">
            {{ __('preparation.refresh') }}
        </flux:button>
    </div>

    <flux:callout x-cloak x-show="!online" variant="warning" icon="signal-slash" class="callout-contrast" role="status">
        <flux:callout.text>{{ __('preparation.offline') }}</flux:callout.text>
    </flux:callout>
    <flux:callout x-cloak x-show="online && revalidating" variant="info" icon="arrow-path" role="status">
        <flux:callout.text>{{ __('preparation.rechecking') }}</flux:callout.text>
    </flux:callout>
    <flux:callout x-cloak x-show="online && refreshFailed" variant="danger" icon="exclamation-circle" role="status">
        <flux:callout.text>{{ __('preparation.refresh_failed') }}</flux:callout.text>
    </flux:callout>

    <div class="prep-views">
        <flux:tabs aria-label="{{ __('ui.departments.dashboard.filter') }}">
            <flux:tab name="active" data-preparation-view="active" wire:click="setView('active')" :selected="$activeView === 'active'"
                wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled">{{ __('preparation.views.active') }}</flux:tab>
            <flux:tab name="ready" data-preparation-view="ready" wire:click="setView('ready')" :selected="$activeView === 'ready'"
                wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled">{{ __('preparation.views.ready') }}</flux:tab>
            <flux:tab name="history" data-preparation-view="history" wire:click="setView('history')" :selected="$activeView === 'history'"
                wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled">{{ __('preparation.views.history') }}</flux:tab>
        </flux:tabs>
        @if ($activeView !== 'ready')
            <flux:select wire:model.live="ticketFilter" :label="__('ui.departments.dashboard.filter')" class="prep-subfilter"
                wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled">
                @if ($activeView === 'active')
                    <flux:select.option value="active">{{ __('preparation.views.active') }}</flux:select.option>
                    <flux:select.option value="new">{{ __('statuses.kitchen_ticket_item.new') }}</flux:select.option>
                    <flux:select.option value="accepted">{{ __('statuses.kitchen_ticket_item.accepted') }}</flux:select.option>
                    <flux:select.option value="in_progress">{{ __('statuses.kitchen_ticket_item.in_progress') }}</flux:select.option>
                @else
                    <flux:select.option value="history">{{ __('preparation.views.history') }}</flux:select.option>
                    <flux:select.option value="completed">{{ __('ui.departments.dashboard.completed') }}</flux:select.option>
                    <flux:select.option value="cancelled">{{ __('statuses.kitchen_ticket_item.cancelled') }}</flux:select.option>
                @endif
            </flux:select>
        @endif
    </div>

    <div class="prep-counts">
        <p><strong>{{ $ticketCountLabel }}</strong> · {{ $portionCountLabel }}</p>
        <flux:button type="button" variant="subtle" wire:click="$set('ticketFilter', 'cancelled')"
            wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled">
            {{ __('preparation.open_cancellation_history') }}
        </flux:button>
    </div>

    @if ($recentCancelledItemCount > 0)
        <p class="prep-item__cancellation">{{ __('preparation.recent_cancelled', ['count' => $recentCancelledItemCount]) }}</p>
    @endif
    @if ($routingIssueCount > 0)
        <flux:callout variant="warning" icon="exclamation-triangle" class="callout-contrast">
            <flux:callout.heading>{{ __('preparation.routing.title') }}</flux:callout.heading>
            <flux:callout.text>{{ __('preparation.routing.help') }}</flux:callout.text>
            <p>{{ __('preparation.routing.count', ['count' => $routingIssueCount]) }}</p>
            <ul>
                @forelse ($routingIssues as $issue)
                    <li>{{ __('preparation.order', ['number' => $issue['order_id']]) }} · {{ __('preparation.ticket', ['number' => $issue['ticket_id']]) }} · {{ $issue['department_name'] }} ({{ $issue['department_type_label'] }})</li>
                @empty
                @endforelse
            </ul>
        </flux:callout>
    @endif

    @if ($feedbackMessage)
        <p data-preparation-feedback tabindex="-1" role="status" class="prep-feedback">{{ $feedbackMessage }}</p>
    @endif
    @error('ticket_item_status')
        <flux:callout variant="danger" icon="exclamation-circle" role="alert"><flux:callout.text>{{ $message }}</flux:callout.text></flux:callout>
    @enderror
    <flux:error name="filters.department" /><flux:error name="filters.ticket" /><flux:error name="filters.filter" />

    @if ($selectedTicketId !== '')
        <flux:button type="button" wire:click="closeTicket" icon="arrow-left" class="prep-back"
            wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled">{{ __('preparation.close_ticket') }}</flux:button>
    @endif

    <div data-department-priority-queue data-preparation-queue class="prep-queue">
        @forelse ($tickets as $ticket)
            <article wire:key="preparation-ticket-{{ $ticket['id'] }}" class="prep-ticket" data-preparation-ticket="{{ $ticket['id'] }}">
                <header class="prep-ticket__header">
                    <div class="prep-ticket__identity">
                        <h2 tabindex="-1" data-preparation-ticket-heading>{{ __('preparation.order', ['number' => $ticket['order_id']]) }}</h2>
                        <p>{{ __('preparation.ticket', ['number' => $ticket['id']]) }} · {{ $ticket['department_name'] }}</p>
                        @if (! $ticket['is_department_active'])
                            <p class="prep-ticket__inactive">{{ __('preparation.inactive_department') }}</p>
                        @endif
                    </div>
                    <div class="prep-ticket__place">
                        <strong>{{ __('preparation.current_place', ['place' => $ticket['service_point_label']]) }}</strong>
                        <p>{{ __('guest.table.zone') }}: {{ $ticket['zone_name'] ?? __('qr.filters.no_zone') }}</p>
                        @if ($ticket['original_service_point_id'] !== $ticket['service_point_id'])
                            <p>{{ __('preparation.original_place', ['place' => $ticket['original_service_point_label']]) }}</p>
                        @endif
                    </div>
                    <flux:button icon="printer" :href="route('restaurant.departments.tickets.print', $ticket['id'])"
                        data-preparation-print class="prep-control">{{ __('preparation.print_full') }}</flux:button>
                    <div class="prep-ticket__state">
                        <flux:badge :color="$ticket['work_status']['color']">{{ __('preparation.full_status', ['status' => $ticket['work_status']['label']]) }}</flux:badge>
                        <p>{{ __('ui.departments.dashboard.sent_at') }}: {{ $ticket['sent_at'] ?? __('ui.departments.dashboard.time_not_set') }}</p>
                    </div>
                    <div data-kitchen-delay-timer data-elapsed-seconds="{{ $ticket['elapsed_seconds'] }}"
                        data-attention-after-seconds="{{ $ticket['attention_after_seconds'] }}" data-delayed-after-seconds="{{ $ticket['delayed_after_seconds'] }}"
                        data-delay-state="{{ $ticket['delay_state'] }}" data-timer-stopped="{{ $ticket['timer_running'] ? 'false' : 'true' }}"
                        data-timer-phase="{{ $ticket['timer_phase'] }}" data-timer-known="{{ $ticket['timer_known'] ? 'true' : 'false' }}"
                        data-label-on-track="{{ __('ui.departments.dashboard.delay_status.on_track') }}" data-label-attention="{{ __('ui.departments.dashboard.delay_status.attention') }}"
                        data-label-delayed="{{ __('ui.departments.dashboard.delay_status.delayed') }}" data-label-unknown="{{ __('preparation.timer.unknown') }}"
                        data-delay-template="{{ __('ui.departments.dashboard.delay_by', ['time' => ':time']) }}" class="prep-timer">
                        <span>{{ __('preparation.timer.'.$ticket['timer_phase']) }}</span>
                        @if ($ticket['timer_known'])
                            <time data-kitchen-delay-value datetime="PT{{ $ticket['elapsed_seconds'] }}S">{{ $ticket['elapsed_label'] }}</time>
                        @else
                            <span data-kitchen-delay-unknown>{{ __('preparation.timer.unknown') }}</span>
                        @endif
                        <span data-kitchen-delay-status>{{ $ticket['delay_status_label'] }}</span>
                        <span data-kitchen-delay-overrun @if ($ticket['delay_description'] === null) hidden @endif>{{ $ticket['delay_description'] }}</span>
                    </div>
                </header>

                @if ($ticket['is_partial'])
                    <div class="prep-ticket__scope">
                        <p>{{ __('preparation.partial', ['shown' => $ticket['item_count'], 'total' => $ticket['full_item_count'], 'matching' => $ticket['matching_item_count']]) }}</p>
                        @if ($selectedTicketId === '')
                            <flux:button type="button" wire:click="openTicket({{ $ticket['id'] }})" wire:offline.attr="disabled" wire:loading.attr="disabled"
                                x-bind:disabled="commandsDisabled">{{ __('preparation.open_ticket') }}</flux:button>
                        @endif
                    </div>
                @endif

                @forelse ($ticket['items'] as $item)
                    <section id="preparation-item-{{ $item['id'] }}" wire:key="preparation-item-{{ $item['id'] }}"
                        data-preparation-item="{{ $item['id'] }}" data-ticket-item-status="{{ $item['status_value'] }}" class="prep-item">
                        <div class="prep-item__content">
                            <div class="prep-item__title">
                                <strong class="prep-item__quantity">{{ $item['quantity'] }}×</strong>
                                <div>
                                    <h3>{{ $item['item_name'] }}</h3>
                                    @if ($item['variant_name'])
                                        <p class="prep-item__variant">{{ __('preparation.variant') }}: {{ $item['variant_name'] }}</p>
                                    @endif
                                    <flux:badge :color="$item['status_color']">{{ $item['status_label'] }}</flux:badge>
                                </div>
                            </div>
                            @if ($item['modifiers'] !== [])
                                <div class="prep-item__extras">
                                    <strong>{{ __('ui.departments.dashboard.modifiers') }}</strong>
                                    <ul>
                                        @forelse ($item['modifiers'] as $modifier)
                                            <li>{{ $modifier['label'] }}</li>
                                        @empty
                                        @endforelse
                                    </ul>
                                </div>
                            @endif
                            <div role="note" class="prep-item__allergens">
                                <strong>{{ __('ui.departments.dashboard.allergens') }}</strong>
                                @if ($item['allergens'] !== [])
                                    <ul>
                                        @forelse ($item['allergens'] as $allergen)
                                            <li>{{ $allergen['label'] }}</li>
                                        @empty
                                        @endforelse
                                    </ul>
                                @else
                                    <p>{{ __('preparation.allergens_unknown') }}</p>
                                @endif
                            </div>
                            @if ($item['comment'])
                                <div class="prep-item__comment">
                                    <strong>{{ __('ui.departments.dashboard.comment') }}</strong>
                                    <x-ui.plain-text :text="$item['comment']" />
                                </div>
                            @endif
                            @if ($item['completed_at'])
                                <p>{{ __('ui.departments.dashboard.completed_at') }}: {{ $item['completed_at'] }}</p>
                            @endif
                            @if ($item['cancellation_reason'])
                                <p class="prep-item__cancellation"><strong>{{ __('ui.departments.dashboard.cancellation_reason') }}:</strong> {{ $item['cancellation_reason'] }}</p>
                            @endif
                        </div>
                        <div class="prep-item__actions">
                            @if ($item['primary_transition'])
                                <flux:button type="button" variant="primary" class="prep-primary"
                                    data-preparation-transition="{{ $item['primary_transition']['value'] }}"
                                    wire:click="setItemStatus({{ $item['id'] }}, '{{ $item['primary_transition']['value'] }}', '{{ $item['status_value'] }}', '{{ $item['version'] }}', {{ $ticket['id'] }})"
                                    wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled">
                                    {{ $item['primary_transition']['label'] }}
                                </flux:button>
                                <flux:checkbox wire:model.live="selection.itemIds" value="{{ $item['id'] }}"
                                    :label="__('preparation.selection.select', ['name' => $item['item_name']])"
                                    wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled" />
                            @endif
                            @if ($item['secondary_transitions'] !== [])
                                <flux:dropdown>
                                    <flux:button type="button" icon:trailing="chevron-down" variant="subtle"
                                        wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled">{{ __('preparation.more_actions') }}</flux:button>
                                    <flux:menu>
                                        @forelse ($item['secondary_transitions'] as $transition)
                                            <flux:menu.item wire:click="setItemStatus({{ $item['id'] }}, '{{ $transition['value'] }}', '{{ $item['status_value'] }}', '{{ $item['version'] }}', {{ $ticket['id'] }})"
                                                data-preparation-transition="{{ $transition['value'] }}" wire:offline.attr="disabled" x-bind:disabled="commandsDisabled">{{ $transition['label'] }}</flux:menu.item>
                                        @empty
                                        @endforelse
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                            @if (isset($itemFeedback[$item['id']]))
                                <p class="prep-item__feedback">{{ $itemFeedback[$item['id']] }}</p>
                            @endif
                            @if ($item['notification_delivery_pending'] || isset($pendingNotifications[$item['id']]))
                                <p class="prep-item__feedback">{{ __('preparation.notifications.pending') }}</p>
                                @if ($item['can_retry_notification'] ?? false)
                                    <flux:button type="button" wire:click="retryNotification({{ $item['id'] }})" data-preparation-retry-notification
                                        wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled">{{ __('preparation.notifications.retry') }}</flux:button>
                                @endif
                            @endif
                        </div>
                    </section>
                @empty
                    <p class="prep-ticket__scope">{{ __('ui.departments.ticket_print.no_ticket_items_yet') }}</p>
                @endforelse

                @if ($selectedTicketId !== '' && ($ticket['has_previous_item_page'] || $ticket['has_next_item_page']))
                    <nav class="prep-pagination" aria-label="{{ __('ui.departments.dashboard.pagination') }}">
                        <flux:button wire:click="$set('itemPage', {{ $ticket['item_page'] - 1 }})" :disabled="! $ticket['has_previous_item_page']"
                            wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled || {{ $ticket['has_previous_item_page'] ? 'false' : 'true' }}">{{ __('pagination.previous') }}</flux:button>
                        <p>{{ __('ui.departments.dashboard.page', ['page' => $ticket['item_page']]) }}</p>
                        <flux:button wire:click="$set('itemPage', {{ $ticket['item_page'] + 1 }})" :disabled="! $ticket['has_next_item_page']"
                            wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled || {{ $ticket['has_next_item_page'] ? 'false' : 'true' }}">{{ __('pagination.next') }}</flux:button>
                    </nav>
                @endif
            </article>
        @empty
            <x-ui.empty-state icon="clipboard-document-list" :heading="$emptyMessage" :description="$activeFilterLabel" />
        @endforelse
    </div>

    @if ($hasPreviousTicketPage || $hasNextTicketPage)
        <nav class="prep-pagination" aria-label="{{ __('ui.departments.dashboard.pagination') }}">
            <flux:button wire:click="previousTicketPage" :disabled="! $hasPreviousTicketPage" class="prep-control"
                wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled || {{ $hasPreviousTicketPage ? 'false' : 'true' }}">{{ __('pagination.previous') }}</flux:button>
            <p>{{ __('ui.departments.dashboard.page', ['page' => $ticketPage]) }}</p>
            <flux:button wire:click="nextTicketPage" :disabled="! $hasNextTicketPage" class="prep-control"
                wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled || {{ $hasNextTicketPage ? 'false' : 'true' }}">{{ __('pagination.next') }}</flux:button>
        </nav>
    @endif

    <form wire:submit="reviewSelection" class="prep-selection" novalidate>
        <div><strong>{{ __('preparation.selection.selected', ['count' => $selectionCount]) }}</strong><p>{{ __('preparation.selection.hint') }}</p></div>
        @if ($selectionCount > 0)
            <p>{{ __('preparation.selection.frozen') }}</p>
        @endif
        <flux:select wire:model="selection.status" :label="__('preparation.selection.target')"
            wire:offline.attr="disabled" x-bind:disabled="commandsDisabled">
            <flux:select.option value="accepted">{{ __('ui.departments.dashboard.accept') }}</flux:select.option>
            <flux:select.option value="in_progress">{{ __('ui.departments.dashboard.start_preparing') }}</flux:select.option>
            <flux:select.option value="ready">{{ __('ui.departments.dashboard.mark_ready') }}</flux:select.option>
        </flux:select>
        <flux:error name="selection.itemIds" /><flux:error name="selection.status" />
        <div class="prep-selection__actions">
            <flux:button type="submit" variant="primary" data-preparation-selection-review class="prep-primary"
                :disabled="$selectionCount === 0" wire:offline.attr="disabled" wire:loading.attr="disabled"
                x-bind:disabled="commandsDisabled || {{ $selectionCount === 0 ? 'true' : 'false' }}">{{ __('preparation.selection.review') }}</flux:button>
            <flux:button type="button" wire:click="clearSelection" wire:offline.attr="disabled" wire:loading.attr="disabled"
                x-bind:disabled="commandsDisabled">{{ __('preparation.selection.clear') }}</flux:button>
        </div>
    </form>

    @if ($batchResults !== [])
        <section class="prep-results" aria-labelledby="preparation-results-heading" tabindex="-1" data-preparation-feedback>
            <h2 id="preparation-results-heading">{{ __('preparation.selection.results') }}</h2>
            <ul>@forelse ($batchResults as $result)<li>{{ __('preparation.row_result', ['id' => $result['id'], 'message' => $result['message']]) }}</li>@empty @endforelse</ul>
        </section>
    @endif

    <flux:modal name="preparation-review" :dismissible="false" class="prep-review">
        <h2 id="preparation-review-title" x-bind="dialogLabel">{{ __('preparation.selection.title') }}</h2>
        <p>{{ __('preparation.selection.summary', ['ticket' => $reviewTicketId, 'rows' => $reviewRowCount, 'portions' => $reviewPortionCount]) }}</p>
        <ol class="prep-review__items">
            @forelse ($reviewItems as $item)
                <li><strong>{{ $item['quantity'] }}× {{ $item['item_name'] }}</strong> · {{ $item['department_name'] }}
                    @if ($item['variant_name'])<p>{{ __('preparation.variant') }}: {{ $item['variant_name'] }}</p>@endif
                    @if ($item['modifiers'] !== [])<ul>@forelse ($item['modifiers'] as $modifier)<li>{{ $modifier['label'] }}</li>@empty @endforelse</ul>@endif
                    <div role="note" class="prep-item__allergens"><strong>{{ __('ui.departments.dashboard.allergens') }}</strong>
                        @if ($item['allergens'] !== [])<ul>@forelse ($item['allergens'] as $allergen)<li>{{ $allergen['label'] }}</li>@empty @endforelse</ul>
                        @else<p>{{ __('preparation.allergens_unknown') }}</p>@endif
                    </div>
                    @if ($item['comment'])<p><strong>{{ __('ui.departments.dashboard.comment') }}:</strong> {{ $item['comment'] }}</p>@endif
                </li>
            @empty
            @endforelse
        </ol>
        <div class="prep-selection__actions">
            <flux:modal.close><flux:button type="button" autofocus>{{ __('preparation.selection.close') }}</flux:button></flux:modal.close>
            <flux:button type="button" variant="primary" wire:click="applySelection" data-preparation-selection-apply class="prep-primary"
                wire:offline.attr="disabled" wire:loading.attr="disabled" x-bind:disabled="commandsDisabled">{{ __('preparation.selection.apply', ['status' => $reviewStatusLabel]) }}</flux:button>
        </div>
    </flux:modal>
</section>
