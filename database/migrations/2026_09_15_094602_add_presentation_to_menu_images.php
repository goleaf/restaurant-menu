<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->json('image_presentation')->nullable()->after('image');
        });
        Schema::table('menu_item_images', function (Blueprint $table): void {
            $table->json('presentation')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('menu_item_images', function (Blueprint $table): void {
            $table->dropColumn('presentation');
        });
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('image_presentation');
        });
    }
};
