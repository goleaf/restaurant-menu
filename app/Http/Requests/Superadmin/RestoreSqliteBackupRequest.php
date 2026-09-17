<?php

declare(strict_types=1);

namespace App\Http\Requests\Superadmin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class RestoreSqliteBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return [...$this->request->all(), ...$this->allFiles()];
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return ['grant' => ['required', 'string', 'regex:/^[A-Za-z0-9]{64}$/D'], 'backup' => ['prohibited'], 'path' => ['prohibited']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'grant' => __('validation.attributes.sqlite_backup'),
        ];
    }
}
