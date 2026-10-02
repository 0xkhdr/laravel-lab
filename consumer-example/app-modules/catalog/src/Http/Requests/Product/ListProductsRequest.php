<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'region_id' => ['nullable', 'uuid', 'exists:regions,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
