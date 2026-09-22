<?php

namespace App\Http\Requests\Api;

use App\Enums\WarehouseStatus;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
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
        /** @var Warehouse $warehouse */
        $warehouse = $this->route('warehouse');

        return [
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('warehouses', 'code')->ignore($warehouse->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(WarehouseStatus::class)],
        ];
    }
}
