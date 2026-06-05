<?php

namespace App\Support\BusinessRules;

use App\Enums\BusinessRuleCode;
use App\Exceptions\BusinessRuleViolation;

final readonly class BusinessRuleResult
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function __construct(
        public bool $allowed,
        public ?BusinessRuleCode $rule = null,
        public string $field = 'business_rule',
        public ?string $message = null,
        public array $context = [],
    ) {}

    public static function allowed(): self
    {
        return new self(true);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function denied(
        BusinessRuleCode $rule,
        string $field = 'business_rule',
        ?string $message = null,
        array $context = [],
    ): self {
        return new self(false, $rule, $field, $message, $context);
    }

    public function throwIfDenied(): void
    {
        if ($this->allowed || ! $this->rule instanceof BusinessRuleCode) {
            return;
        }

        throw BusinessRuleViolation::for(
            rule: $this->rule,
            field: $this->field,
            message: $this->message,
            context: $this->context,
        );
    }
}
