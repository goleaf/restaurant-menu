<?php

declare(strict_types=1);

namespace App\Data\Menus;

final readonly class MenuItemData
{
    /**
     * @param  list<string>|null  $allergens
     * @param  list<string>|null  $dietaryLabels
     * @param  array<string, array{name?: string|null, description?: string|null}>|null  $translations
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $weight,
        public ?string $volume,
        public ?int $calories,
        public int $sortOrder,
        public string|int|null $price = null,
        public ?array $allergens = null,
        public ?array $dietaryLabels = null,
        public ?bool $isAvailable = null,
        public ?string $hiddenUntil = null,
        public bool $updatesHiddenUntil = false,
        public ?array $translations = null,
    ) {}

    /**
     * @param  array{name: string, description: string|null, weight: string|null, volume: string|null, calories: int|null, sort_order: int, price?: string|int, allergens?: list<string>, dietary_labels?: list<string>, is_available?: bool, hidden_until?: string|null, translations?: array<string, array{name?: string|null, description?: string|null}>}  $values
     */
    public static function fromValidated(array $values): self
    {
        return new self(
            name: $values['name'],
            description: $values['description'],
            weight: $values['weight'],
            volume: $values['volume'],
            calories: $values['calories'],
            sortOrder: $values['sort_order'],
            price: $values['price'] ?? null,
            allergens: $values['allergens'] ?? null,
            dietaryLabels: $values['dietary_labels'] ?? null,
            isAvailable: $values['is_available'] ?? null,
            hiddenUntil: $values['hidden_until'] ?? null,
            updatesHiddenUntil: array_key_exists('hidden_until', $values),
            translations: $values['translations'] ?? null,
        );
    }
}
