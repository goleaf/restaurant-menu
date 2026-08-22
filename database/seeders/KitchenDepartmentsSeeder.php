<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class KitchenDepartmentsSeeder extends Seeder
{
    public function __construct(
        private readonly SeedKitchenDepartmentsForBranchAction $seedBranchDepartments,
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Branch::query()
            ->select(['id'])
            ->lazyById()
            ->each(function (Branch $branch): void {
                $this->seedBranchDepartments->handle($branch);
            });
    }
}
