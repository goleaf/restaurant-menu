<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Staff;

class Index extends \App\Livewire\Organizations\Staff\Index
{
    protected function isBranchWorkspace(): bool
    {
        return true;
    }

    protected function viewName(): string
    {
        return 'livewire.organizations.brands.branches.staff.index';
    }
}
