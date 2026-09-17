<?php

declare(strict_types=1);

use App\Models\RestaurantOnboarding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_onboardings', function (Blueprint $table): void {
            $table->dropUnique(['user_id']);
            $table->dropUnique(['organization_id']);
            $table->dropUnique(['brand_id']);
            $table->uuid('creation_key')->nullable()->unique();
            $table->string('creation_hash', 64)->nullable();
            $table->string('purpose', 20)->default('first');
            $table->unsignedBigInteger('setup_version')->default(0);
            $table->index(['user_id', 'completed_at', 'id']);
            $table->index('organization_id');
            $table->index('brand_id');
        });
    }

    public function down(): void
    {
        if (RestaurantOnboarding::query()->whereNotNull('creation_key')->orWhere('purpose', 'additional')->exists()) {
            throw new RuntimeException('Restaurant creation receipts and additional attempts require a reviewed forward migration.');
        }

        Schema::table('restaurant_onboardings', function (Blueprint $table): void {
            $table->unique('user_id');
            $table->unique('organization_id');
            $table->unique('brand_id');
            $table->dropIndex(['user_id', 'completed_at', 'id']);
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['brand_id']);
            $table->dropUnique(['creation_key']);
            $table->dropColumn(['creation_key', 'creation_hash', 'purpose', 'setup_version']);
        });
    }
};
