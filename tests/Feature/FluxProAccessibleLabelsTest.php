<?php

declare(strict_types=1);

use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

test('local Pro calendars distinguish translated previous next and today controls', function (string $locale, array $labels, string $component): void {
    app()->setLocale($locale);
    view()->share('errors', new ViewErrorBag);
    $html = Blade::render('<flux:'.$component.' name="report-date" value="2026-09-16" with-today :locale="$locale" />', ['locale' => $locale]);
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

    expect($document->querySelector('ui-calendar-previous')?->getAttribute('aria-label'))->toBe($labels['previous'])
        ->and($document->querySelector('ui-calendar-next')?->getAttribute('aria-label'))->toBe($labels['next'])
        ->and($document->querySelector('ui-calendar-today')?->getAttribute('aria-label'))->toBe($labels['today'])
        ->and($document->querySelector('ui-calendar-today')?->getAttribute('aria-label'))->not->toBe($labels['previous']);
})->with('local Pro accessible label locales')->with(['calendar', 'date-picker']);

test('local Pro nested search controls distinguish translated clear from close', function (string $locale, array $labels, string $component, bool $closable): void {
    app()->setLocale($locale);
    view()->share('errors', new ViewErrorBag);
    $variant = $component === 'select' ? ' variant="listbox"' : '';
    $template = '<flux:'.$component.' name="branch"'.$variant.' searchable>'
        .'<x-slot:search><flux:'.$component.'.search :closable="$closable ? true : null" /></x-slot:search>'
        .'<flux:'.$component.'.option value="one">Example branch</flux:'.$component.'.option>'
        .'</flux:'.$component.'>';
    $document = HTMLDocument::createFromString(Blade::render($template, ['closable' => $closable]), LIBXML_NOERROR);
    $search = $document->querySelector('[data-flux-'.$component.'-search]');

    expect($search)->not->toBeNull()
        ->and($search->querySelector('input')?->getAttribute('placeholder'))->toBe(__('layout.search'))
        ->and($search->querySelectorAll('button'))->toHaveCount(1)
        ->and($search->querySelector('button')?->getAttribute('aria-label'))->toBe($labels[$closable ? 'close' : 'clear'])
        ->and($search->querySelector('ui-close') !== null)->toBe($closable);
})->with('local Pro accessible label locales')->with([
    'select clear' => ['select', false],
    'select close' => ['select', true],
    'pillbox clear' => ['pillbox', false],
    'pillbox close' => ['pillbox', true],
]);

test('local Pro date controls translate range inputs placeholders and confirmation actions', function (string $locale, array $labels): void {
    app()->setLocale($locale);
    view()->share('errors', new ViewErrorBag);
    $calendar = HTMLDocument::createFromString(Blade::render('<flux:calendar name="period" mode="range" with-inputs :locale="$locale" />', ['locale' => $locale]), LIBXML_NOERROR);
    $range = HTMLDocument::createFromString(Blade::render('<flux:date-picker name="period" mode="range" with-inputs with-confirmation presets="today" :locale="$locale" />', ['locale' => $locale]), LIBXML_NOERROR);
    $single = HTMLDocument::createFromString(Blade::render('<flux:date-picker name="day" with-confirmation :locale="$locale" />', ['locale' => $locale]), LIBXML_NOERROR);

    foreach ([$calendar, $range] as $document) {
        $inputLabels = array_map(fn ($label): string => trim($label->textContent), iterator_to_array($document->querySelectorAll('ui-calendar-inputs span')));
        expect($inputLabels)->toBe([$labels['start'], $labels['end']]);
    }
    foreach ([[$range, $labels['range']], [$single, $labels['date']]] as [$document, $expectedPlaceholder]) {
        $placeholder = HTMLDocument::createFromString($document->querySelector('ui-selected-date template[name="placeholder"]')->innerHTML, LIBXML_NOERROR);
        expect(trim($placeholder->querySelector('[data-flux-date-picker-placeholder]')->textContent))->toBe($expectedPlaceholder);
    }
    expect(trim($range->querySelector('ui-date-picker-select button')->textContent))->toBe($labels['range'])
        ->and(trim($single->querySelector('ui-date-picker-select button')->textContent))->toBe($labels['date'])
        ->and(trim($range->querySelector('ui-close button')->textContent))->toBe(__('ui.actions.cancel'))
        ->and(trim($single->querySelector('ui-close button')->textContent))->toBe(__('ui.actions.cancel'))
        ->and(trim($range->querySelector('ui-calendar-presets select option')->textContent))->toBe($labels['preset']);
})->with([
    'English' => ['en', ['start' => 'Start', 'end' => 'End', 'date' => 'Select date', 'range' => 'Select range', 'preset' => 'Choose predefined range…']],
    'Lithuanian' => ['lt', ['start' => 'Pradžia', 'end' => 'Pabaiga', 'date' => 'Pasirinkti datą', 'range' => 'Pasirinkti laikotarpį', 'preset' => 'Pasirinkti iš anksto nustatytą laikotarpį…']],
    'Russian' => ['ru', ['start' => 'Начало', 'end' => 'Конец', 'date' => 'Выбрать дату', 'range' => 'Выбрать период', 'preset' => 'Выбрать готовый период…']],
]);

dataset('local Pro accessible label locales', [
    'English' => ['en', ['previous' => 'Previous month', 'next' => 'Next month', 'today' => 'Today', 'clear' => 'Clear search', 'close' => 'Close search']],
    'Lithuanian' => ['lt', ['previous' => 'Ankstesnis mėnuo', 'next' => 'Kitas mėnuo', 'today' => 'Šiandien', 'clear' => 'Išvalyti paiešką', 'close' => 'Uždaryti paiešką']],
    'Russian' => ['ru', ['previous' => 'Предыдущий месяц', 'next' => 'Следующий месяц', 'today' => 'Сегодня', 'clear' => 'Очистить поиск', 'close' => 'Закрыть поиск']],
]);
