<?php

use App\Enums\ServicePointStatus;
use App\Models\ServicePoint;
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
        Schema::table('service_points', function (Blueprint $table) {
            $table->string('status', 40)->default(ServicePointStatus::Free->value)->change();
        });

        ServicePoint::query()
            ->where('status', 'available')
            ->update(['status' => ServicePointStatus::Free->value]);

        ServicePoint::query()
            ->where('status', 'unavailable')
            ->update(['status' => ServicePointStatus::Blocked->value]);

        ServicePoint::query()
            ->where('status', 'maintenance')
            ->update(['status' => ServicePointStatus::Blocked->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        ServicePoint::query()
            ->where('status', ServicePointStatus::Free->value)
            ->update(['status' => 'available']);

        ServicePoint::query()
            ->whereIn('status', [
                ServicePointStatus::WaitingWaiter->value,
                ServicePointStatus::HasNewOrder->value,
                ServicePointStatus::Cooking->value,
                ServicePointStatus::ReadyToServe->value,
                ServicePointStatus::PaymentRequested->value,
                ServicePointStatus::Paid->value,
                ServicePointStatus::Closed->value,
                ServicePointStatus::Blocked->value,
            ])
            ->update(['status' => 'unavailable']);

        Schema::table('service_points', function (Blueprint $table) {
            $table->string('status', 40)->default('available')->change();
        });
    }
};
