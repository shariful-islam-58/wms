<?php

namespace App\Http\Requests\Api;

use App\Enums\LocationStatus;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
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
        /** @var Location $location */
        $location = $this->route('location');

        return [
            'code' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('locations', 'code')
                    ->where(fn ($query) => $query->where('warehouse_id', $location->warehouse_id))
                    ->ignore($location->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(LocationStatus::class)],
        ];
    }
}
