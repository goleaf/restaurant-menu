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
        Schema::table('branches', function (Blueprint $table) {
            $table->string('public_name')->nullable()->after('name');
            $table->text('public_description')->nullable()->after('public_name');
            $table->string('cover_image_path', 512)->nullable()->after('logo_path');
            $table->string('phone')->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
            $table->string('website_url', 2048)->nullable()->after('email');
            $table->string('instagram_url', 2048)->nullable()->after('website_url');
            $table->string('facebook_url', 2048)->nullable()->after('instagram_url');
            $table->string('tiktok_url', 2048)->nullable()->after('facebook_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'public_name',
                'public_description',
                'cover_image_path',
                'phone',
                'email',
                'website_url',
                'instagram_url',
                'facebook_url',
                'tiktok_url',
            ]);
        });
    }
};
