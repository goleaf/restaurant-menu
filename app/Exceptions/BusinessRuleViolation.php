<?php

namespace App\Exceptions;

use App\Enums\ApplicationErrorType;
use App\Enums\BusinessRuleCode;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BusinessRuleViolation extends ValidationException implements ShouldntReport
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly BusinessRuleCode $businessRule,
        private readonly string $field,
        ?string $message = null,
        private readonly array $context = [],
    ) {
        $validator = Validator::make([], []);
        $validator->errors()->add($field, $message ?? $businessRule->message());

        parent::__construct($validator);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function for(
        BusinessRuleCode $rule,
        string $field = 'business_rule',
        ?string $message = null,
        array $context = [],
    ): self {
        return new self($rule, $field, $message, $context);
    }

    public function businessRule(): BusinessRuleCode
    {
        return $this->businessRule;
    }

    public function errorType(): ApplicationErrorType
    {
        return $this->businessRule->errorType();
    }

    public function field(): string
    {
        return $this->field;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
