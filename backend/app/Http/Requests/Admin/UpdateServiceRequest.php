<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
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
        $serviceId = $this->route('service')?->id;

        return [
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('service_categories', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:140', Rule::unique('services', 'slug')->ignore($serviceId)],
            'short_description' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_per_hour' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999.99'],
            'default_duration_hours' => ['sometimes', 'required', 'numeric', 'min:0.5', 'max:24'],
            'min_duration_hours' => ['sometimes', 'required', 'numeric', 'min:0.5', 'max:24'],
            'max_duration_hours' => ['sometimes', 'required', 'numeric', 'min:0.5', 'max:24'],
            'is_live' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ];
    }
}
