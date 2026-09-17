<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Media\RemoveLocalImageAction;
use App\Actions\Media\ReplaceLocalImageAction;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class UpdateOrganizationLogoAction
{
    public function __construct(
        private readonly ReplaceLocalImageAction $replaceLocalImage,
        private readonly RemoveLocalImageAction $removeLocalImage,
    ) {}

    public function handle(Organization $organization, ?UploadedFile $file, ?User $actor = null, ?string $expectedMediaFingerprint = null): Organization
    {
        $current = DB::transaction(function () use ($organization, $file, $actor, $expectedMediaFingerprint): Organization {
            $current = Organization::query()
                ->select(['id', 'owner_user_id', 'logo_path', 'updated_at', 'deleted_at'])
                ->lockForUpdate()
                ->findOrFail($organization->getKey());

            if ($actor !== null) {
                Gate::forUser(User::query()->whereKey($actor->id)->firstOrFail())->authorize('update', $current);
            }
            if ($expectedMediaFingerprint !== null && ! hash_equals($expectedMediaFingerprint, hash('sha256', (string) $current->logo_path))) {
                throw ValidationException::withMessages(['logo' => __('center.conflict')]);
            }

            if ($file instanceof UploadedFile) {
                $this->replaceLocalImage->handle(
                    file: $file,
                    directory: "media/organizations/{$current->id}/logos",
                    oldPath: $current->logo_path,
                    persist: function (string $path) use ($current): void {
                        if ($current->forceFill(['logo_path' => $path])->save() !== true) {
                            throw new RuntimeException('The image reference could not be saved.');
                        }
                    },
                );
            } else {
                $this->removeLocalImage->handle(
                    oldPath: $current->logo_path,
                    persist: function () use ($current): void {
                        if ($current->forceFill(['logo_path' => null])->save() !== true) {
                            throw new RuntimeException('The image reference could not be saved.');
                        }
                    },
                );
            }

            return $current;
        });

        $organization->forceFill($current->only(['logo_path', 'updated_at']))
            ->syncOriginalAttributes(['logo_path', 'updated_at']);

        return $organization;
    }
}
