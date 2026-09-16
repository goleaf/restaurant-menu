<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

test('menu label cards preserve deferred bindings and associate nested errors in each locale', function (string $locale) {
    app()->setLocale($locale);
    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
        'editingItemAllergens.0' => 'Invalid allergen selection',
        'editingItemDietaryLabels' => 'Invalid dietary selection',
    ])));

    $html = Blade::render(<<<'BLADE'
        <x-menu.item-label-fields
            allergens-model="editingItemAllergens" dietary-labels-model="editingItemDietaryLabels" id-prefix="edit-dish-12"
            :allergen-options="[['value' => 'milk', 'label' => 'Milk']]"
            :dietary-label-options="[['value' => 'vegan', 'label' => 'Vegan']]"
        />
    BLADE);
    $document = new DOMDocument;
    @$document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($document);

    foreach (['allergens' => 'editingItemAllergens', 'dietary' => 'editingItemDietaryLabels'] as $group => $model) {
        $control = $xpath->query('//ui-checkbox-group[@*[name()="wire:model" and .="'.$model.'"]]')->item(0);
        expect($control)->not->toBeNull();
        expect($control->getAttribute('aria-describedby'))->toBe('edit-dish-12-'.$group.'-help edit-dish-12-'.$group.'-error');
        $error = $xpath->query('//*[@id="edit-dish-12-'.$group.'-error"]')->item(0);
        expect($error?->textContent)->toContain($group === 'allergens' ? 'Invalid allergen selection' : 'Invalid dietary selection');
        $card = $xpath->query('//ui-checkbox-group[@*[name()="wire:model" and .="'.$model.'"]]//ui-checkbox')->item(0);
        expect($card?->getAttribute('aria-describedby'))->toBe($control->getAttribute('aria-describedby'));
        expect($card?->getAttribute('aria-invalid'))->toBe('true');
        expect($card?->hasAttribute('data-flux-checkbox-buttons'))->toBeTrue();
    }
    expect($html)->not->toContain('wire:model.live', 'type="checkbox"', 'menu.allergens.title', 'menu.dietary_labels.title');
})->with(['en', 'lt', 'ru']);
