<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Models\Branch;
use App\Models\User;
use App\Support\Branches\BranchPublicContent;
use App\Support\PlainText;
use App\Support\Validation\Branches\BranchProfileRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class UpdateBranchPublicProfileAction
{
    public function __construct(
        private readonly ExecuteBranchSettingsChangeAction $execute,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function handle(User $actor, Branch $branch, array $data, string $expectedFingerprint, string $requestId): array
    {
        $data = Validator::make($data, BranchProfileRules::publicProfilePayload(), attributes: BranchProfileRules::publicProfileAttributes(payload: true))->validate();

        return $this->execute->handle($actor, $branch, 'profile', $requestId, [
            'expected' => $expectedFingerprint, 'data' => $data,
        ], function (User $actor, Branch $branch) use ($data, $expectedFingerprint): array {
            if (! hash_equals($expectedFingerprint, self::fingerprint($branch))) {
                throw ValidationException::withMessages(['profileForm.publicName' => __('settings.errors.conflict')]);
            }
            $before = self::values($branch);
            $changes = [];
            foreach (BranchProfileRules::publicProfileFields() as $column) {
                if (array_key_exists($column, $data)) {
                    $limit = match ($column) {
                        'public_name' => 160, 'public_description' => 1200, 'phone' => 80, 'email' => 255, default => 2048,
                    };
                    $changes[$column] = PlainText::optional($data[$column], $limit, squish: $column !== 'public_description');
                }
            }
            if (array_key_exists('public_translations', $data)) {
                $translations = BranchPublicContent::translations($branch) ?? [];
                Validator::make(
                    ['profileForm' => ['translations' => $translations]],
                    BranchProfileRules::publicTranslations('profileForm.translations'),
                    attributes: BranchProfileRules::publicProfileAttributes('profileForm.'),
                )->validate();
                foreach ($data['public_translations'] as $language => $values) {
                    foreach ($values as $field => $value) {
                        $translations[$language][$field] = PlainText::optional($value, $field === 'name' ? 160 : 1200, squish: $field === 'name');
                    }
                }
                $changes['public_translations'] = $translations;
            }
            $branch->fill($changes);
            if ($branch->isDirty(self::fields())) {
                if ($branch->save() !== true) {
                    throw new RuntimeException('The restaurant public profile could not be saved.');
                }
                $this->recordAuditLog->handle(
                    action: AuditLogAction::BranchSettingsChanged, entityType: 'branch', entityId: $branch->id,
                    actorUser: $actor, organizationId: $branch->organization_id, branchId: $branch->id,
                    oldValues: ['group' => 'profile', ...$before], newValues: ['group' => 'profile', ...self::values($branch)],
                );
            }

            return ['fingerprint' => self::fingerprint($branch)];
        });
    }

    public static function fingerprint(Branch $branch): string
    {
        $values = self::values($branch);
        $translations = $values['public_translations'] ?? null;
        if (is_array($translations)) {
            ksort($translations);
            foreach ($translations as &$translation) {
                if (is_array($translation)) {
                    ksort($translation);
                }
            }
            unset($translation);
            $values['public_translations'] = ['type' => 'translations', 'value' => $translations];
        } elseif (is_string($stored = $branch->getAttributes()['public_translations'] ?? null)) {
            $values['public_translations'] = ['type' => 'invalid', 'fingerprint' => hash('sha256', $stored)];
        } else {
            $values['public_translations'] = ['type' => 'absent'];
        }

        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    /** @return list<string> */
    public static function fields(): array
    {
        return [...array_values(BranchProfileRules::publicProfileFields()), 'public_translations'];
    }

    /** @return array<string, mixed> */
    private static function values(Branch $branch): array
    {
        return [...$branch->only(self::fields()), 'public_translations' => BranchPublicContent::translations($branch)];
    }
}
