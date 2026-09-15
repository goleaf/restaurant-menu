<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Menus;

use Livewire\Attributes\Url;
use Livewire\Form;

class CatalogFilterForm extends Form
{
    #[Url(as: 'q', history: true)]
    public mixed $search = '';

    #[Url(as: 'availability', history: true)]
    public mixed $availability = '';

    #[Url(as: 'menu', history: true)]
    public mixed $menuId = '';

    #[Url(as: 'quality', history: true)]
    public mixed $quality = '';

    #[Url(as: 'page', history: true, except: 1)]
    public mixed $page = 1;

    public function searchTerm(): string
    {
        return is_string($this->search) ? mb_substr(trim($this->search), 0, 200) : '';
    }

    public function availabilityValue(): string
    {
        return in_array($this->availability, ['available', 'unavailable'], true) ? $this->availability : '';
    }

    public function menuSelection(): string
    {
        return (is_string($this->menuId) || is_int($this->menuId)) && preg_match('/^[1-9][0-9]{0,17}$/D', (string) $this->menuId) === 1 ? (string) $this->menuId : '';
    }

    public function qualityValue(): string
    {
        return in_array($this->quality, ['photo', 'description', 'translations', 'publication'], true) ? $this->quality : '';
    }

    public function pageNumber(): int
    {
        return (is_int($this->page) || is_string($this->page)) && ctype_digit((string) $this->page) ? max(1, min(10000, (int) $this->page)) : 1;
    }

    /** @return array{search: string, availability: string, menu: string, quality: string, page: int} */
    public function normalized(): array
    {
        return ['search' => $this->searchTerm(), 'availability' => $this->availabilityValue(), 'menu' => $this->menuSelection(), 'quality' => $this->qualityValue(), 'page' => $this->pageNumber()];
    }
}
