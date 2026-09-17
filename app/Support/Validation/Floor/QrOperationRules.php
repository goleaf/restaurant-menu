<?php

declare(strict_types=1);

namespace App\Support\Validation\Floor;

use Illuminate\Validation\Rule;

final class QrOperationRules
{
    /** @return array<string,list<mixed>> */
    public static function rules(): array
    {
        return [
            'operation' => ['required', Rule::in(['generate', 'disable', 'reissue', 'repair'])],
            'qrId' => ['nullable', 'required_unless:operation,generate', 'integer', 'min:1'],
            'expectedVersion' => ['nullable', 'required_unless:operation,generate', 'integer', 'min:0'],
            'requestId' => ['required', 'uuid'],
            'reason' => ['nullable', 'required_if:operation,disable', 'string', 'min:3', 'max:500'],
            'confirmation' => ['required_if:operation,reissue', 'string', 'max:24'],
        ];
    }

    /** @return array<string,string> */
    public static function attributes(): array
    {
        return [
            'operation' => __('floor.fields.operation'),
            'qrId' => __('qr.labels.title'),
            'expectedVersion' => __('floor.fields.version'),
            'requestId' => __('floor.fields.request'),
            'reason' => __('qr.labels.disable_reason'),
            'confirmation' => __('qr.labels.current_short_code'),
        ];
    }
}
