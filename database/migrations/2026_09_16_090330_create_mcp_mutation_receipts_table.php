<?php

declare(strict_types=1);

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
        Schema::create('mcp_mutation_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mcp_access_token_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->index()->constrained()->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->string('ability', 64);
            $table->string('input_hash', 64);
            $table->json('result');
            $table->timestamps();
            $table->unique(['mcp_access_token_id', 'idempotency_key'], 'mcp_receipts_token_attempt_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_mutation_receipts');
    }
};
