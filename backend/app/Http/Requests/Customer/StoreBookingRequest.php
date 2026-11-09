<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
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
        $customerId = $this->user()?->customer?->id;

        return [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_live', true)],
            'address_id' => ['required', 'integer', Rule::exists('addresses', 'id')->where('customer_id', $customerId)],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
