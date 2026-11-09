<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'customer_since' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'addresses' => ['array'],
            'addresses.*.label' => ['required_with:addresses', 'string', 'max:60'],
            'addresses.*.line1' => ['required_with:addresses', 'string', 'max:255'],
            'addresses.*.line2' => ['nullable', 'string', 'max:255'],
            'addresses.*.area' => ['required_with:addresses', 'string', 'max:120'],
            'addresses.*.city' => ['required_with:addresses', 'string', 'max:120'],
            'addresses.*.access_instructions' => ['nullable', 'string', 'max:1000'],
            'addresses.*.is_default' => ['boolean'],
            'send_invite' => ['boolean'],
        ];
    }
}
