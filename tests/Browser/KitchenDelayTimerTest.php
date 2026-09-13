<?php

declare(strict_types=1);

test('kitchen delay timers advance locally and expose accessible status changes', function () {
    $this->withVite();

    $page = visit(route('login', absolute: false));
    $labels = json_encode([
        'onTrack' => __('ui.departments.dashboard.delay_status.on_track'),
        'attention' => __('ui.departments.dashboard.delay_status.attention'),
        'delayed' => __('ui.departments.dashboard.delay_status.delayed'),
        'delayTemplate' => __('ui.departments.dashboard.delay_by', ['time' => ':time']),
    ], JSON_THROW_ON_ERROR);

    $timerScript = <<<'JAVASCRIPT'
        (() => {
            const labels = __LABELS__;
            const timerMarkup = (id, elapsed, stopped = false) => `
                <div
                    id="${id}"
                    data-kitchen-delay-timer
                    data-elapsed-seconds="${elapsed}"
                    data-timer-stopped="${stopped}"
                    data-attention-after-seconds="600"
                    data-delayed-after-seconds="900"
                    data-delay-state="on-track"
                    data-label-on-track="${labels.onTrack}"
                    data-label-attention="${labels.attention}"
                    data-label-delayed="${labels.delayed}"
                    data-delay-template="${labels.delayTemplate}"
                >
                    <time data-kitchen-delay-value datetime="PT${elapsed}S">00:00</time>
                    <p data-kitchen-delay-status role="status"></p>
                    <p data-kitchen-delay-overrun hidden></p>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', timerMarkup('attention-timer', 600));
            document.body.insertAdjacentHTML('beforeend', timerMarkup('delayed-timer', 905));
            document.body.insertAdjacentHTML('beforeend', timerMarkup('completed-timer', 300, true));
            document.dispatchEvent(new CustomEvent('livewire:navigated'));
        })()
    JAVASCRIPT;

    $page->script(str_replace('__LABELS__', $labels, $timerScript));

    $page->script('new Promise((resolve) => window.setTimeout(resolve, 2200))');

    $state = $page->script(<<<'JAVASCRIPT'
        (() => {
            const attention = document.querySelector('#attention-timer');
            const delayed = document.querySelector('#delayed-timer');
            const completed = document.querySelector('#completed-timer');
            const delayedOverrun = delayed?.querySelector('[data-kitchen-delay-overrun]');

            return {
                attentionAdvanced: attention?.querySelector('[data-kitchen-delay-value]')?.textContent !== '10:00',
                attentionReady: attention?.dataset.kitchenDelayTimerReady,
                attentionState: attention?.dataset.delayState,
                attentionStatus: attention?.querySelector('[data-kitchen-delay-status]')?.textContent,
                completedValue: completed?.querySelector('[data-kitchen-delay-value]')?.textContent,
                delayedOverrunHidden: delayedOverrun?.hidden,
                delayedOverrunText: delayedOverrun?.textContent,
                delayedReady: delayed?.dataset.kitchenDelayTimerReady,
                delayedState: delayed?.dataset.delayState,
                delayedStatus: delayed?.querySelector('[data-kitchen-delay-status]')?.textContent,
            };
        })()
    JAVASCRIPT);

    expect($state['attentionAdvanced'])->toBeTrue()
        ->and($state['attentionReady'])->toBe('true')
        ->and($state['attentionState'])->toBe('attention')
        ->and($state['attentionStatus'])->toBe(__('ui.departments.dashboard.delay_status.attention'))
        ->and($state['completedValue'])->toBe('05:00')
        ->and($state['delayedOverrunHidden'])->toBeFalse()
        ->and($state['delayedOverrunText'])->toStartWith(__('ui.departments.dashboard.delay_by', ['time' => '']))
        ->and($state['delayedReady'])->toBe('true')
        ->and($state['delayedState'])->toBe('delayed')
        ->and($state['delayedStatus'])->toBe(__('ui.departments.dashboard.delay_status.delayed'));

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
