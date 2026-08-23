<?php

declare(strict_types=1);

namespace Database\Seeders;

final class DemoMenuTranslations
{
    /** @return array<string, array{name: string, description: string}> */
    public static function category(string $name, string $description): array
    {
        return match ($name) {
            'Пицца' => [
                'en' => ['name' => 'Pizza', 'description' => 'Classic pizza for a quick demo order.'],
                'lt' => ['name' => 'Picos', 'description' => 'Klasikinės picos greitam demo užsakymui.'],
                'ru' => ['name' => $name, 'description' => $description],
            ],
            'Напитки' => [
                'en' => ['name' => 'Drinks', 'description' => 'Hot and cold drinks.'],
                'lt' => ['name' => 'Gėrimai', 'description' => 'Karšti ir šalti gėrimai.'],
                'ru' => ['name' => $name, 'description' => $description],
            ],
            'Десерты' => [
                'en' => ['name' => 'Desserts', 'description' => 'A sweet finish to the order.'],
                'lt' => ['name' => 'Desertai', 'description' => 'Saldus užsakymo užbaigimas.'],
                'ru' => ['name' => $name, 'description' => $description],
            ],
            'Роллы' => [
                'en' => ['name' => 'Rolls', 'description' => 'Rolls and nigiri prepared after ordering.'],
                'lt' => ['name' => 'Suktinukai', 'description' => 'Suktinukai ir nigiri ruošiami gavus užsakymą.'],
                'ru' => ['name' => $name, 'description' => $description],
            ],
            'Кофе и выпечка' => [
                'en' => ['name' => 'Coffee and pastries', 'description' => 'Freshly roasted coffee and fresh pastries.'],
                'lt' => ['name' => 'Kava ir kepiniai', 'description' => 'Šviežiai skrudinta kava ir švieži kepiniai.'],
                'ru' => ['name' => $name, 'description' => $description],
            ],
            default => self::fallback($name, $description),
        };
    }

    /** @return array<string, array{name: string, description: string}> */
    public static function item(string $name, string $description): array
    {
        return match ($name) {
            'Маргарита' => self::translations('Margherita', 'Tomato sauce, mozzarella, basil.', 'Margarita', 'Pomidorų padažas, mocarela, bazilikas.', $name, $description),
            'Пепперони' => self::translations('Pepperoni', 'Spicy pepperoni, mozzarella, tomato sauce.', 'Pepperoni', 'Aitri pepperoni dešra, mocarela, pomidorų padažas.', $name, $description),
            'Капричоза' => self::translations('Capricciosa', 'Ham, mushrooms, artichokes, and mozzarella.', 'Capricciosa', 'Kumpis, grybai, artišokai ir mocarela.', $name, $description),
            'Домашний лимонад' => self::translations('Homemade lemonade', 'Lemon, mint, ice, and sparkling water.', 'Naminis limonadas', 'Citrina, mėta, ledas ir gazuotas vanduo.', $name, $description),
            'Эспрессо' => self::translations('Espresso', 'Classic double espresso.', 'Espresas', 'Klasikinis dvigubas espresas.', $name, $description),
            'Тирамису' => self::translations('Tiramisu', 'Mascarpone, coffee, and cocoa.', 'Tiramisu', 'Maskarponė, kava ir kakava.', $name, $description),
            'Чизкейк' => self::translations('Cheesecake', 'Creamy cheesecake with berry sauce.', 'Sūrio pyragas', 'Kreminis sūrio pyragas su uogų padažu.', $name, $description),
            'Филадельфия' => self::translations('Philadelphia', 'Salmon, cream cheese, cucumber, and rice.', 'Philadelphia', 'Lašiša, kreminis sūris, agurkas ir ryžiai.', $name, $description),
            'Калифорния' => self::translations('California', 'Crab, avocado, cucumber, and tobiko.', 'California', 'Krabas, avokadas, agurkas ir tobiko ikrai.', $name, $description),
            'Нигири с лососем' => self::translations('Salmon nigiri', 'Salmon and rice, two pieces.', 'Nigiri su lašiša', 'Lašiša ir ryžiai, du vienetai.', $name, $description),
            'Капучино' => self::translations('Cappuccino', 'Espresso and steamed milk.', 'Kapučinas', 'Espresas ir plakintas pienas.', $name, $description),
            'Флэт уайт' => self::translations('Flat white', 'Double espresso with a thin layer of milk foam.', 'Flat white', 'Dvigubas espresas su plonu pieno putos sluoksniu.', $name, $description),
            'Круассан' => self::translations('Croissant', 'A buttery croissant baked today.', 'Kruasanas', 'Šiandien keptas sviestinis kruasanas.', $name, $description),
            default => self::fallback($name, $description),
        };
    }

    /** @return array<string, array{name: string, description: string}> */
    private static function translations(
        string $englishName,
        string $englishDescription,
        string $lithuanianName,
        string $lithuanianDescription,
        string $russianName,
        string $russianDescription,
    ): array {
        return [
            'en' => ['name' => $englishName, 'description' => $englishDescription],
            'lt' => ['name' => $lithuanianName, 'description' => $lithuanianDescription],
            'ru' => ['name' => $russianName, 'description' => $russianDescription],
        ];
    }

    /** @return array<string, array{name: string, description: string}> */
    private static function fallback(string $name, string $description): array
    {
        return self::translations($name, $description, $name, $description, $name, $description);
    }
}
