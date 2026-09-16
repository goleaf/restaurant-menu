<?php

declare(strict_types=1);

use App\Actions\Media\StoreLocalImageAction;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

function imageUploadDocument(string $html): HTMLDocument
{
    return HTMLDocument::createFromString('<!doctype html><html><body>'.$html.'</body></html>');
}

beforeEach(function (): void {
    view()->share('errors', new ViewErrorBag);
});

test('image upload associates translated guidance and forwards browser and Livewire attributes', function (string $locale): void {
    app()->setLocale($locale);
    $html = Blade::render(<<<'BLADE'
        <p id="gallery-limit">Eight photos</p>
        <x-ui.image-upload-input id="gallery-upload" name="photos" wire:model.live="itemImageUploads.42" wire:key="upload-42" x-ref="files" x-bind:disabled="busy" @change.capture="preview($event)" aria-describedby="gallery-limit" aria-label="Dish photos" multiple disabled required class="extra-class" />
    BLADE);
    $document = imageUploadDocument($html);
    $input = $document->querySelector('input[type="file"]');

    expect($input->getAttribute('aria-describedby'))->toBe('gallery-limit gallery-upload-help gallery-upload-error')
        ->and(trim($document->getElementById('gallery-upload-help')->textContent))->toBe(StoreLocalImageAction::helpText())
        ->and($document->getElementById('gallery-upload-error')->hasAttribute('data-flux-error'))->toBeTrue()
        ->and($input->getAttribute('accept'))->toBe(StoreLocalImageAction::acceptedMimeTypes())
        ->and($input->getAttribute('name'))->toBe('photos')
        ->and($input->getAttribute('wire:model.live'))->toBe('itemImageUploads.42')
        ->and($input->getAttribute('wire:key'))->toBe('upload-42')
        ->and($input->getAttribute('x-ref'))->toBe('files')
        ->and($input->getAttribute('x-bind:disabled'))->toBe('busy')
        ->and($input->getAttribute('@change.capture'))->toBe('preview($event)')
        ->and($input->getAttribute('class'))->toContain('extra-class', 'rm-image-upload')
        ->and($input->hasAttribute('multiple'))->toBeTrue()
        ->and($input->hasAttribute('disabled'))->toBeTrue()
        ->and($input->hasAttribute('required'))->toBeTrue();
})->with(['en', 'lt', 'ru']);

test('image upload gives a stable distinct identity to model-bound controls without explicit ids', function (): void {
    $template = '<x-ui.image-upload-input wire:model="form.publicLogo" aria-label="Logo" /><x-ui.image-upload-input wire:model="form.coverImage" aria-label="Cover" />';
    $first = imageUploadDocument(Blade::render($template));
    $second = imageUploadDocument(Blade::render($template));
    $inputs = $first->querySelectorAll('input');

    expect($inputs->item(0)->id)->not->toBeEmpty()->not->toBe($inputs->item(1)->id)
        ->and($inputs->item(0)->id)->toBe($second->querySelector('input')->id);

    foreach ($inputs as $input) {
        foreach (explode(' ', $input->getAttribute('aria-describedby')) as $id) {
            expect($first->getElementById($id))->not->toBeNull();
        }
    }
});

test('image upload names its own error and honors an explicit error name without duplicating descriptions', function (): void {
    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
        'form.image' => 'Image rejected',
        'photoValidation' => '<script>Invalid image</script>',
    ])));
    $html = Blade::render(<<<'BLADE'
        <x-ui.image-upload-input id="upload" wire:model="form.image" error-name="photoValidation" aria-label="Photo" aria-describedby="upload-help external-hint upload-help" />
    BLADE);
    $document = imageUploadDocument($html);
    $input = $document->querySelector('input');

    expect($input->getAttribute('aria-describedby'))->toBe('upload-help external-hint upload-error')
        ->and($input->getAttribute('aria-invalid'))->toBe('true')
        ->and($document->getElementById('upload-error')->textContent)->toContain('<script>Invalid image</script>')
        ->and($document->querySelector('script'))->toBeNull()
        ->and($html)->not->toContain('Image rejected');
});

test('image upload uses its model for field errors while leaving per-file errors to the gallery', function (): void {
    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
        'photos' => 'Choose fewer images',
        'photos.0' => 'First photo too large',
    ])));
    $document = imageUploadDocument(Blade::render('<x-ui.image-upload-input wire:model="photos" aria-label="Photos" />'));

    expect($document->querySelector('input')->getAttribute('aria-invalid'))->toBe('true')
        ->and($document->querySelector('[data-flux-error]')->textContent)->toContain('Choose fewer images')->not->toContain('First photo too large');
});

test('image upload without a model still has guidance and preserves caller invalid state', function (): void {
    $document = imageUploadDocument(Blade::render('<x-ui.image-upload-input aria-label="Photo" aria-invalid="true" />'));
    $input = $document->querySelector('input');

    expect($input->id)->not->toBeEmpty()
        ->and($input->getAttribute('aria-invalid'))->toBe('true')
        ->and($input->getAttribute('aria-describedby'))->toBe($input->id.'-help')
        ->and($document->getElementById($input->id.'-help'))->not->toBeNull()
        ->and($document->querySelector('[data-flux-error]'))->toBeNull();
});

test('image upload marks nested file errors invalid without duplicating the gallery messages', function (): void {
    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
        'photos.0' => 'First photo too large',
    ])));
    $document = imageUploadDocument(Blade::render('<x-ui.image-upload-input id="photos" wire:model="photos" aria-label="Photos" aria-describedby="photo-errors" />'));

    expect($document->querySelector('input')->getAttribute('aria-invalid'))->toBe('true')
        ->and($document->querySelector('input')->getAttribute('aria-describedby'))->toContain('photo-errors')
        ->and(trim($document->querySelector('[data-flux-error]')->textContent))->toBe('');
});
