<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Menus;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

final class CatalogTransferForm extends Form
{
    public mixed $menuId = '';

    public mixed $file = null;

    public function selectedMenu(): int
    {
        $this->validate(['menuId' => ['required', 'numeric', 'integer', 'min:1']], [], ['menuId' => __('menu.guest.title')]);

        return (int) $this->menuId;
    }

    public function contents(): string
    {
        $this->validate(['file' => ['required', 'file', 'max:1024']], [], ['file' => __('menu.csv.file')]);
        if (! $this->file instanceof UploadedFile) {
            throw ValidationException::withMessages(['form.file' => __('menu.csv.errors.encoding_size')]);
        }

        return $this->file->get();
    }
}
