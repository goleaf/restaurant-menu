<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Floor;

use Livewire\Form;

class QrOperationForm extends Form
{
    public mixed $reason = '';

    public mixed $confirmation = '';

    /** @return array{reason:string,confirmation:string} */
    public function payload(string $operation): array
    {
        $data = $this->validate(['reason' => [$operation === 'disable' ? 'required' : 'nullable', 'string', 'min:3', 'max:500'],
            'confirmation' => [$operation === 'reissue' ? 'required' : 'nullable', 'string', 'max:24']],
            attributes: ['reason' => __('floor.fields.reason'), 'confirmation' => __('floor.fields.confirmation')]);

        return ['reason' => $data['reason'] ?? '', 'confirmation' => $data['confirmation'] ?? ''];
    }
}
