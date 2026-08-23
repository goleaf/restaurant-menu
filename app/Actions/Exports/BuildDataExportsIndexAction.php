<?php

namespace App\Actions\Exports;

use App\Enums\DataExportType;
use App\Models\Branch;
use App\Models\User;

class BuildDataExportsIndexAction
{
    public function __construct(
        private readonly ResolveExportAccessibleBranchIdsAction $resolveExportAccessibleBranchIds,
    ) {}

    /**
     * @return array{has_access: bool, branches: list<array<string, mixed>>, export_types: list<array{value: string, label: string}>}
     */
    public function handle(User $user): array
    {
        $branchIds = $this->resolveExportAccessibleBranchIds->handle($user);

        if ($branchIds->isEmpty()) {
            return [
                'has_access' => false,
                'branches' => [],
                'export_types' => $this->exportTypes(),
            ];
        }

        $branches = Branch::query()
            ->select(['id', 'organization_id', 'brand_id', 'name', 'city', 'country', 'currency'])
            ->with([
                'organization:id,name',
                'brand:id,name',
            ])
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Branch $branch): array => $this->branchRow($branch))
            ->all();

        return [
            'has_access' => true,
            'branches' => $branches,
            'export_types' => $this->exportTypes(),
        ];
    }

    public function userHasAccess(User $user): bool
    {
        return $this->resolveExportAccessibleBranchIds->handle($user)->isNotEmpty();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function exportTypes(): array
    {
        return array_map(
            fn (DataExportType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            DataExportType::cases(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function branchRow(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'organization_name' => $branch->organization?->name,
            'brand_name' => $branch->brand?->name,
            'location' => collect([$branch->city, $branch->country])
                ->filter(fn (?string $value): bool => filled($value))
                ->implode(', '),
            'currency' => $branch->currency ?: 'EUR',
            'downloads' => collect(DataExportType::cases())
                ->mapWithKeys(fn (DataExportType $type): array => [
                    $type->value => route('restaurant.exports.download', [
                        'branch' => $branch,
                        'export' => $type->value,
                    ]),
                ])
                ->all(),
            'pdf_downloads' => collect(DataExportType::cases())
                ->mapWithKeys(fn (DataExportType $type): array => [
                    $type->value => route('restaurant.exports.pdf', [
                        'branch' => $branch,
                        'export' => $type->value,
                    ]),
                ])
                ->all(),
        ];
    }
}
