<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Local;

use App\Enums\SystemRole;
use Illuminate\Validation\Rule;
use Livewire\Form;

final class IdentitySelectionForm extends Form
{
    public mixed $userId = null;

    public mixed $role = null;

    public function localIdentity(mixed $input): int
    {
        $this->userId = $input;
        $data = $this->validate(['userId' => ['required', 'numeric', 'integer', 'min:1']], [], ['userId' => __('local_login.identity')]);

        return (int) $data['userId'];
    }

    public function demoRole(mixed $input): SystemRole
    {
        $this->role = $input;
        $data = $this->validate(['role' => ['required', 'string', Rule::enum(SystemRole::class)]], [], ['role' => __('staff.role')]);

        return SystemRole::from($data['role']);
    }
}
