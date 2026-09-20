<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

final readonly class DisplayPreferences
{
    public const DATE_FORMATS = ['locale', 'd.m.Y', 'd/m/Y', 'm/d/Y', 'Y-m-d'];

    public const TIME_FORMATS = ['locale', '24h', '12h'];

    public const NUMBER_FORMATS = ['locale', 'comma_dot', 'dot_comma', 'space_comma', 'space_dot'];

    private function __construct(
        public string $dateFormat,
        public string $timeFormat,
        public string $numberFormat,
    ) {}

    public static function defaults(): self
    {
        return new self('locale', 'locale', 'locale');
    }

    /** @param array<string, mixed> $values */
    public static function from(array $values): self
    {
        return new self(
            in_array($values['date_format'] ?? null, self::DATE_FORMATS, true) ? $values['date_format'] : 'locale',
            in_array($values['time_format'] ?? null, self::TIME_FORMATS, true) ? $values['time_format'] : 'locale',
            in_array($values['number_format'] ?? null, self::NUMBER_FORMATS, true) ? $values['number_format'] : 'locale',
        );
    }

    public static function forUser(User $user): self
    {
        return self::from($user->getAttributes());
    }

    public static function current(): self
    {
        $user = Auth::hasUser() ? Auth::user() : null;

        return $user instanceof User ? self::forUser($user) : self::defaults();
    }

    public function fingerprint(): string
    {
        return implode(':', [$this->dateFormat, $this->timeFormat, $this->numberFormat]);
    }

    /** @return array{date_format: string, time_format: string, number_format: string} */
    public function values(): array
    {
        return ['date_format' => $this->dateFormat, 'time_format' => $this->timeFormat, 'number_format' => $this->numberFormat];
    }

    /** @return array{decimal: string, group: string}|null */
    public function numberSeparators(): ?array
    {
        return match ($this->numberFormat) {
            'comma_dot' => ['decimal' => '.', 'group' => ','],
            'dot_comma' => ['decimal' => ',', 'group' => '.'],
            'space_comma' => ['decimal' => ',', 'group' => "\u{00A0}"],
            'space_dot' => ['decimal' => '.', 'group' => "\u{00A0}"],
            default => null,
        };
    }
}
