<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['area_nodes', 'service_points', 'qr_codes'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->unsignedBigInteger('structure_version')->default(0);
            });
        }
        Schema::create('floor_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 40);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('payload_hash', 64);
            $table->json('result');
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floor_operations');
        foreach (['area_nodes', 'service_points', 'qr_codes'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('structure_version'));
        }
    }
};
