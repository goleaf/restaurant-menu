<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Organizations;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Support\Validation\Branches\BranchProfileRules;
use App\Support\Validation\Organizations\OrganizationRules;
use Illuminate\Validation\Rule;
use Livewire\Form;

final class RestaurantIdentityForm extends Form
{
    public mixed $name = '';

    public mixed $address = '';

    public mixed $city = '';

    public mixed $country = '';

    public mixed $timezone = '';

    public mixed $currency = '';

    public mixed $isActive = false;

    public function load(Organization|Brand|Branch $resource): void
    {
        $this->name = $resource->name;
        if ($resource instanceof Branch) {
            $this->fill($resource->only(['address', 'city', 'country', 'timezone', 'currency']));
            $this->isActive = $resource->is_active;
        }
    }

    /** @return array<string,mixed> */
    public function validated(Organization|Brand|Branch $resource): array
    {
        $rules = $resource instanceof Branch ? BranchProfileRules::branchBase() : OrganizationRules::organizationName('name');
        $parent = match (true) {
            $resource instanceof Branch => 'brand_id', $resource instanceof Brand => 'organization_id', default => 'owner_user_id',
        };
        $rules['name'][] = Rule::unique($resource->getTable(), 'name')->where($parent, $resource->getAttribute($parent))->ignore($resource);

        return $this->validate($rules, [], ['name' => __('center.name'), 'address' => __('center.address'), 'city' => __('center.city'), 'country' => __('center.country'), 'timezone' => __('center.timezone'), 'currency' => __('center.currency'), 'isActive' => __('center.administrative_active')]);
    }
}
