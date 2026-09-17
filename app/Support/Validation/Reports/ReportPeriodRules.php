<?php

declare(strict_types=1);

namespace App\Support\Validation\Reports;

use App\Data\Reports\ReportPeriodInput;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

final readonly class ReportPeriodRules
{
    public function __construct(private string $timezone, private CarbonImmutable $now) {}

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $data = array_intersect_key($input, self::rules());
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        return $data;
    }

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'date_from' => ['bail', 'nullable', 'string', 'date_format:Y-m-d'],
            'date_to' => ['bail', 'nullable', 'string', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'date_from' => __('validation.attributes.date_from'),
            'date_to' => __('validation.attributes.date_to'),
        ];
    }

    public function __invoke(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        try {
            $data = $validator->getData();
            (new ReportPeriodInput($data['date_from'] ?? null, $data['date_to'] ?? null))->resolve($this->timezone, $this->now);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $validator->errors()->merge([$field => $messages]);
            }
        }
    }
}
