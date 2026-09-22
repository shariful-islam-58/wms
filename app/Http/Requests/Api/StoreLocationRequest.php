<?php

namespace App\Http\Requests\Api;

use App\Enums\LocationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isWmsAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $warehouseId = $this->route('warehouse')?->id ?? $this->integer('warehouse_id');

        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('locations', 'code')->where(fn ($query) => $query->where('warehouse_id', $warehouseId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(LocationStatus::class)],
        ];
    }
}
