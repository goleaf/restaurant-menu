<?php

use App\Models\PermissionUserOverride;
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
        Schema::table('permission_user_overrides', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->string('scope_key')->default('legacy');
            $table->dropUnique(['user_id', 'permission_id']);
            $table->unique(['user_id', 'permission_id', 'scope_key'], 'permission_overrides_context_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (PermissionUserOverride::query()->whereNotNull('organization_id')->exists()) {
            throw new RuntimeException('Scoped permission decisions require a reviewed forward migration; rollback would discard tenant access data.');
        }

        Schema::table('permission_user_overrides', function (Blueprint $table) {
            $table->dropUnique('permission_overrides_context_unique');
            $table->dropIndex(['organization_id']);
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('scope_key');
            $table->unique(['user_id', 'permission_id']);
        });
    }
};
