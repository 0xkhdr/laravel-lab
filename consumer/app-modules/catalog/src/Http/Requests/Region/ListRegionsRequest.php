<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests\Region;

use Illuminate\Foundation\Http\FormRequest;

class ListRegionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
