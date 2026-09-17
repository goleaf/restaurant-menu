<?php

use App\Models\MenuOperation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (MenuOperation::query()->whereNull('menu_id')->exists()) {
            throw new LogicException('Branch-scoped receipts must be retained; this migration cannot be reversed while they exist.');
        }
        Schema::table('menu_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_id')->nullable(false)->change();
        });
    }
};
