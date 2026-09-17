<?php

declare(strict_types=1);

namespace App\Actions\ServicePoints\Support;

use App\Enums\BusinessRuleCode;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Branch;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class ServicePointMutationGuard
{
    public function actor(?User $actor): User
    {
        $actor ??= Auth::user();
        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }

        return User::query()->whereKey($actor->id)->firstOrFail();
    }

    public function branch(int $branchId): Branch
    {
        return Branch::query()->whereKey($branchId)->firstOrFail();
    }

    public function version(ServicePoint $point, ?int $expectedVersion): void
    {
        if ($expectedVersion !== null && $point->structure_version !== $expectedVersion) {
            throw ValidationException::withMessages(['expectedVersion' => __('floor.errors.structure_changed')]);
        }
    }

    public function idle(ServicePoint $point, string $field = 'servicePoint'): void
    {
        if ($point->getAttribute('unfinished_session_exists') || $point->getAttribute('unfinished_link_exists')) {
            throw BusinessRuleViolation::for(BusinessRuleCode::ServicePointHasActiveSession, $field, __('floor.errors.active_session'));
        }
        if ($point->getAttribute('active_order_exists') || $point->getAttribute('linked_active_order_exists')) {
            throw BusinessRuleViolation::for(BusinessRuleCode::StructureHasActiveOrder, $field, __('floor.errors.active_order'));
        }
    }
}
