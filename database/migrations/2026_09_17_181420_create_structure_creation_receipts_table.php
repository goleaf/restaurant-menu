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
        Schema::create('structure_creation_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('request_key');
            $table->string('payload_hash', 64);
            $table->string('kind', 20);
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('resource_id');
            $table->timestamps();
            $table->unique(['actor_id', 'request_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('structure_creation_receipts');
    }
};
