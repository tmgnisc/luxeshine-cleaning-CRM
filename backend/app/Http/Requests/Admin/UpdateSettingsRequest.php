<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'site_title' => ['sometimes', 'required', 'string', 'max:120'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hero_headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hero_subline' => ['sometimes', 'nullable', 'string', 'max:500'],
            'support_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'support_email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'office_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'booking_lead_time_hours' => ['sometimes', 'required', 'integer', 'min:0', 'max:168'],
            'cancellation_cutoff_hours' => ['sometimes', 'required', 'integer', 'min:0', 'max:168'],
            'accepting_bookings' => ['sometimes', 'boolean'],
            'maintenance_mode' => ['sometimes', 'boolean'],
            'maintenance_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'business_open_time' => ['sometimes', 'required', 'date_format:H:i'],
            'business_close_time' => ['sometimes', 'required', 'date_format:H:i', 'after:business_open_time'],
            'service_areas' => ['sometimes', 'array'],
            'service_areas.*' => ['string', 'max:120'],
        ];
    }

    /**
     * Storage type for each editable setting key.
     *
     * @return array<string, string>
     */
    public function settingTypes(): array
    {
        return [
            'site_title' => 'string',
            'tagline' => 'string',
            'hero_headline' => 'string',
            'hero_subline' => 'string',
            'support_phone' => 'string',
            'support_email' => 'string',
            'office_address' => 'string',
            'currency' => 'string',
            'booking_lead_time_hours' => 'int',
            'cancellation_cutoff_hours' => 'int',
            'accepting_bookings' => 'bool',
            'maintenance_mode' => 'bool',
            'maintenance_message' => 'string',
            'business_open_time' => 'string',
            'business_close_time' => 'string',
            'service_areas' => 'json',
        ];
    }
}
