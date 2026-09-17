<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_schedule_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('local_date');
            $table->boolean('is_closed')->default(true);
            $table->json('intervals')->nullable();
            $table->unique(['branch_id', 'local_date']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_schedule_exceptions');
    }
};
