<?php

namespace App\Actions\Organizations;

use App\Models\Organization;

class UpdateOrganizationAction
{
    /**
     * @param  array{name: string}  $data
     */
    public function handle(Organization $organization, array $data): Organization
    {
        $organization->fill([
            'name' => $data['name'],
        ]);

        $organization->save();

        return $organization;
    }
}
