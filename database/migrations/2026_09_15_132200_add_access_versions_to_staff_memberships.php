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
        Schema::table('organization_users', function (Blueprint $table) {
            $table->unsignedInteger('access_version')->default(0);
        });

        Schema::table('branch_users', function (Blueprint $table) {
            $table->unsignedInteger('access_version')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_users', function (Blueprint $table) {
            $table->dropColumn('access_version');
        });

        Schema::table('organization_users', function (Blueprint $table) {
            $table->dropColumn('access_version');
        });
    }
};
