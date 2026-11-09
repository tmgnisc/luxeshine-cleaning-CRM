<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:60'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'area' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'access_instructions' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
        ];
    }
}
