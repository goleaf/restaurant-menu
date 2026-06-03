<?php

namespace App\Actions\Organizations;

use App\Models\Organization;

class DeleteOrganizationAction
{
    public function handle(Organization $organization): void
    {
        $organization->delete();
    }
}
