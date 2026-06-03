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
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('logo_path', 512)->nullable()->after('name');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('logo_path', 512)->nullable()->after('name');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('logo_path', 512)->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
