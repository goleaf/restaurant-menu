<?php

declare(strict_types=1);

namespace App\Support\Floor;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class PreparedFloorPrintStore
{
    public function __construct(private Repository $cache) {}

    /** @param array<string,mixed> $snapshot */
    public function put(User $actor, Branch $branch, array $snapshot): string
    {
        if (($snapshot['actor_id'] ?? null) !== $actor->id || ($snapshot['branch_id'] ?? null) !== $branch->id
            || ! is_array($snapshot['items'] ?? null) || count($snapshot['items']) > 100 || strlen(serialize($snapshot)) > 4_000_000) {
            throw ValidationException::withMessages(['previewId' => __('floor.validation.print_selection', ['max' => 100])]);
        }
        $id = (string) Str::uuid();
        $this->cache->put($this->key($actor, $branch, $id), $snapshot, now()->addMinutes(15));

        return $id;
    }

    /** @return array<string,mixed>|null */
    public function find(User $actor, Branch $branch, ?string $id): ?array
    {
        if ($id === null || ! Str::isUuid($id)) {
            return null;
        }
        $snapshot = $this->cache->get($this->key($actor, $branch, $id));

        return is_array($snapshot) && ($snapshot['actor_id'] ?? null) === $actor->id && ($snapshot['branch_id'] ?? null) === $branch->id ? $snapshot : null;
    }

    public function forget(User $actor, Branch $branch, ?string $id): void
    {
        if ($id !== null && Str::isUuid($id)) {
            $this->cache->forget($this->key($actor, $branch, $id));
        }
    }

    private function key(User $actor, Branch $branch, string $id): string
    {
        return 'floor-print:'.$actor->id.':'.$branch->id.':'.$id;
    }
}
