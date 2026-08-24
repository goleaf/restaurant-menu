<?php

declare(strict_types=1);

use App\Enums\SupportedLocale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_session_guests', function (Blueprint $table): void {
            $table->string('locale', 5)->default(SupportedLocale::English->value);
        });

        Schema::table('table_session_join_requests', function (Blueprint $table): void {
            $table->string('locale', 5)->default(SupportedLocale::English->value);
        });
    }

    public function down(): void
    {
        Schema::table('table_session_join_requests', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });

        Schema::table('table_session_guests', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
