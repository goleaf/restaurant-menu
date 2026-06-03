<?php

namespace Database\Seeders;

use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class KitchenDepartmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seedBranchDepartments = app(SeedKitchenDepartmentsForBranchAction::class);

        Branch::query()
            ->select(['id'])
            ->lazyById()
            ->each(function (Branch $branch) use ($seedBranchDepartments): void {
                $seedBranchDepartments->handle($branch);
            });
    }
}
