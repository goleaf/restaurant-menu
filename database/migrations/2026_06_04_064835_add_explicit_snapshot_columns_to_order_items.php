<?php

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('original_menu_item_id')->nullable()->after('menu_item_id');
            $table->string('guest_name_snapshot', 160)->nullable()->after('guest_name');
            $table->string('item_name_snapshot', 180)->nullable()->after('item_name');
            $table->text('item_description_snapshot')->nullable()->after('item_name_snapshot');
            $table->decimal('unit_price_snapshot', 10, 2)->default(0)->after('unit_price');
            $table->json('modifiers_snapshot')->nullable()->after('selected_modifiers');
            $table->json('tax_snapshot')->nullable()->after('modifiers_snapshot');
            $table->json('service_snapshot')->nullable()->after('tax_snapshot');

            $table->index('original_menu_item_id');
        });

        OrderItem::withoutEvents(function (): void {
            OrderItem::query()
                ->select([
                    'id',
                    'menu_item_id',
                    'guest_name',
                    'item_name',
                    'unit_price',
                    'selected_modifiers',
                    'original_menu_item_id',
                    'guest_name_snapshot',
                    'item_name_snapshot',
                    'unit_price_snapshot',
                    'modifiers_snapshot',
                    'tax_snapshot',
                    'service_snapshot',
                ])
                ->orderBy('id')
                ->chunkById(100, function (EloquentCollection $orderItems): void {
                    $orderItems->each(function (OrderItem $orderItem): void {
                        $orderItem
                            ->forceFill([
                                'original_menu_item_id' => $orderItem->menu_item_id,
                                'guest_name_snapshot' => $orderItem->guest_name,
                                'item_name_snapshot' => $orderItem->item_name,
                                'unit_price_snapshot' => $orderItem->unit_price,
                                'modifiers_snapshot' => $orderItem->selected_modifiers ?? [],
                                'tax_snapshot' => [],
                                'service_snapshot' => [],
                            ])
                            ->save();
                    });
                });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex(['original_menu_item_id']);
            $table->dropColumn([
                'service_snapshot',
                'tax_snapshot',
                'modifiers_snapshot',
                'unit_price_snapshot',
                'item_description_snapshot',
                'item_name_snapshot',
                'guest_name_snapshot',
                'original_menu_item_id',
            ]);
        });
    }
};
