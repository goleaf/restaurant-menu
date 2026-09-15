<?php

declare(strict_types=1);

use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

test('dangerous confirmation associates both help and validation errors with its fields', function (string $locale): void {
    app()->setLocale($locale);
    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
        'reason' => __('ui.confirmations.reason.label'),
        'confirmation' => __('ui.confirmations.confirmation_text.label'),
    ])));

    $html = Blade::render(<<<'BLADE'
        <x-dangerous-action-confirmation name="accessible-confirmation" confirm-action="remove" reason-model="reason" confirmation-model="confirmation" confirmation-text="DELETE">
            <x-slot:trigger><flux:button>{{ __('ui.actions.delete') }}</flux:button></x-slot:trigger>
        </x-dangerous-action-confirmation>
    BLADE);
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$html.'</body></html>');

    foreach (['reason', 'confirmation'] as $name) {
        $field = $document->querySelector('[name="'.$name.'"]');
        expect($field->getAttribute('aria-invalid'))->toBe('true');
        $references = preg_split('/\s+/', $field->getAttribute('aria-describedby') ?? '', flags: PREG_SPLIT_NO_EMPTY);
        expect($references)->toContain('dangerous-action-accessible-confirmation-'.$name.'-error');
        foreach ($references as $reference) {
            expect($document->querySelectorAll('#'.$reference)->length)->toBe(1);
        }
    }

    expect($document->querySelector('[name="confirmation"]')->getAttribute('aria-describedby'))
        ->toContain('dangerous-action-accessible-confirmation-confirmation-help');
})->with(['en', 'lt', 'ru']);
