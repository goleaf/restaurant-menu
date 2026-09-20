<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KitchenDepartmentType;
use App\Enums\SupportedLocale;
use Database\Factories\KitchenDepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property KitchenDepartmentType $type
 */
#[Fillable(['type', 'name', 'sort_order', 'is_active'])]
class KitchenDepartment extends Model
{
    /** @use HasFactory<KitchenDepartmentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'kitchen',
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => KitchenDepartmentType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function localizedName(): string
    {
        if ($this->type === KitchenDepartmentType::Custom) {
            return $this->name;
        }

        return in_array($this->name, self::standardNamesFor($this->type), true) ? $this->type->label() : $this->name;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function matchingDisplayName(Builder $query, string $search): Builder
    {
        return $query->select(['id', 'type', 'name', 'is_active'])
            ->where(function (Builder $names) use ($search): void {
                $names->where('name', 'like', '%'.$search.'%');

                foreach (KitchenDepartmentType::cases() as $type) {
                    if ($type === KitchenDepartmentType::Custom || ! str_contains(mb_strtolower($type->label()), mb_strtolower($search))) {
                        continue;
                    }

                    $names->orWhere(fn (Builder $defaults): Builder => $defaults
                        ->where('type', $type->value)
                        ->whereIn('name', self::standardNamesFor($type)));
                }
            });
    }

    /** @return list<string> */
    private static function standardNamesFor(KitchenDepartmentType $type): array
    {
        $key = 'preparation.types.'.$type->value;
        $standardNames = [$type->defaultName()];

        foreach (SupportedLocale::values() as $locale) {
            $standardNames[] = __($key, [], $locale);
        }

        return $standardNames;
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<KitchenTicket, $this>
     */
    public function kitchenTickets(): HasMany
    {
        return $this->hasMany(KitchenTicket::class)
            ->orderBy('sent_at')
            ->orderBy('id');
    }

    /**
     * @return HasManyThrough<KitchenTicketItem, KitchenTicket, $this>
     */
    public function ticketItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            KitchenTicketItem::class,
            KitchenTicket::class,
            'kitchen_department_id',
            'kitchen_ticket_id',
        );
    }
}
