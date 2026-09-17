<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_commands', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('branch_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->string('kind', 64);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->char('payload_hash', 64);
            $table->json('result');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_commands');
    }
};
