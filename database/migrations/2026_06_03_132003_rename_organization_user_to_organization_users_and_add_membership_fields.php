<?php

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
        Schema::rename('organization_user', 'organization_users');

        Schema::table('organization_users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('role_id');
            $table->timestamp('joined_at')->nullable()->after('status');
            $table->foreignId('invited_by_user_id')
                ->nullable()
                ->after('joined_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_users', function (Blueprint $table) {
            $table->dropForeign(['invited_by_user_id']);
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn(['status', 'joined_at', 'invited_by_user_id']);
        });

        Schema::rename('organization_users', 'organization_user');
    }
};
