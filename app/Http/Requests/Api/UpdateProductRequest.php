<?php

namespace App\Http\Requests\Api;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasWmsAccess() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'sku' => ['sometimes', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
