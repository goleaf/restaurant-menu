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
        Schema::table('branches', function (Blueprint $table): void {
            $table->boolean('is_temporarily_closed')->default(false)->index();
            $table->string('temporary_closed_reason')->nullable();
            $table->timestamp('temporary_closed_until')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropIndex(['is_temporarily_closed']);
            $table->dropIndex(['temporary_closed_until']);
            $table->dropColumn([
                'is_temporarily_closed',
                'temporary_closed_reason',
                'temporary_closed_until',
            ]);
        });
    }
};
