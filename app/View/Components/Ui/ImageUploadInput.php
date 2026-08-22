<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Actions\Media\StoreLocalImageAction;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class ImageUploadInput extends Component
{
    public readonly string $acceptedMimeTypes;

    public readonly string $helpText;

    public function __construct(public readonly string $ariaLabel)
    {
        $this->acceptedMimeTypes = StoreLocalImageAction::acceptedMimeTypes();
        $this->helpText = StoreLocalImageAction::helpText();
    }

    public function render(): View
    {
        return view('components.ui.image-upload-input');
    }
}
