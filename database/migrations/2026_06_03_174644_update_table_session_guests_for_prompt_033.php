<?php

use App\Models\TableSessionGuest;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('table_session_guests', function (Blueprint $table) {
            $table->dropIndex('table_session_guests_table_session_id_status_name_index');
            $table->renameColumn('name', 'guest_name');
        });

        Schema::table('table_session_guests', function (Blueprint $table) {
            $table->string('guest_token', 64)->nullable()->after('guest_name');
        });

        TableSessionGuest::query()
            ->select([
                'id',
                'guest_token',
            ])
            ->orderBy('id')
            ->chunkById(100, function (EloquentCollection $guests): void {
                $guests->each(function (TableSessionGuest $guest): void {
                    $guest
                        ->forceFill(['guest_token' => Str::random(64)])
                        ->save();
                });
            });

        Schema::table('table_session_guests', function (Blueprint $table) {
            $table->string('guest_token', 64)->nullable(false)->change();
            $table->unique('guest_token');
            $table->index(['table_session_id', 'status', 'guest_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_session_guests', function (Blueprint $table) {
            $table->dropIndex('table_session_guests_table_session_id_status_guest_name_index');
            $table->dropUnique('table_session_guests_guest_token_unique');
            $table->dropColumn('guest_token');
            $table->renameColumn('guest_name', 'name');
        });

        Schema::table('table_session_guests', function (Blueprint $table) {
            $table->index(['table_session_id', 'status', 'name']);
        });
    }
};
