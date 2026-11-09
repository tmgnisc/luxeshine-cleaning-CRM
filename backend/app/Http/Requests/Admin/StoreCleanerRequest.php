<?php

namespace App\Http\Requests\Admin;

use App\Enums\VerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCleanerRequest extends FormRequest
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
            'joined_at' => ['nullable', 'date'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'verification_status' => ['nullable', Rule::enum(VerificationStatus::class)],
            'verification_notes' => ['nullable', 'string', 'max:1000'],
            'is_available' => ['boolean'],
            'skills' => ['array'],
            'skills.*' => ['integer', 'exists:services,id'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'send_invite' => ['boolean'],
        ];
    }
}
