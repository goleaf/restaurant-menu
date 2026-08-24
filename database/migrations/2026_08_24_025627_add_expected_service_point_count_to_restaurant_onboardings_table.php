<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('restaurant_onboardings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('expected_service_point_count')->nullable()->after('area_node_id');
        });

        $checkpoint = new class extends Model
        {
            public $timestamps = false;

            protected $table = 'restaurant_onboardings';
        };
        $servicePointLink = new class extends Model
        {
            public $timestamps = false;

            protected $table = 'restaurant_onboarding_service_points';
        };

        $checkpoint->newQuery()
            ->select(Schema::getColumnListing('restaurant_onboardings'))
            ->chunkById(500, function (Collection $onboardings) use ($checkpoint, $servicePointLink): void {
                $linkedCounts = $servicePointLink->newQuery()
                    ->select(['restaurant_onboarding_id'])
                    ->whereIn('restaurant_onboarding_id', $onboardings->modelKeys())
                    ->get()
                    ->countBy('restaurant_onboarding_id');
                $updates = $onboardings
                    ->map(function (Model $onboarding) use ($linkedCounts): ?array {
                        $linkedCount = (int) $linkedCounts->get($onboarding->getKey(), 0);

                        if ($linkedCount < 1) {
                            return null;
                        }

                        return [
                            ...$onboarding->getAttributes(),
                            'expected_service_point_count' => $linkedCount,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                if ($updates !== []) {
                    $checkpoint->newQuery()->upsert($updates, ['id'], ['expected_service_point_count']);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_onboardings', function (Blueprint $table): void {
            $table->dropColumn('expected_service_point_count');
        });
    }
};
