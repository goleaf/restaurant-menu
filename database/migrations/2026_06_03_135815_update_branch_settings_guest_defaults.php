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
        Schema::table('branch_settings', function (Blueprint $table): void {
            $table->boolean('allow_guest_created_sessions')->default(true)->change();
            $table->boolean('allow_guest_invite_links')->default(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_settings', function (Blueprint $table): void {
            $table->boolean('allow_guest_created_sessions')->default(false)->change();
            $table->boolean('allow_guest_invite_links')->default(false)->change();
        });
    }
};
