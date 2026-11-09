<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
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
            'category_id' => ['required', 'integer', Rule::exists('service_categories', 'id')],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', Rule::unique('services', 'slug')],
            'short_description' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_per_hour' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'default_duration_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'min_duration_hours' => ['required', 'numeric', 'min:0.5', 'max:24', 'lte:default_duration_hours'],
            'max_duration_hours' => ['required', 'numeric', 'min:0.5', 'max:24', 'gte:default_duration_hours'],
            'is_live' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ];
    }
}
