<?php

namespace App\Http\Requests\Cleaner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWorkingHoursRequest extends FormRequest
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
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'hours.*.is_off' => ['required', 'boolean'],
            'hours.*.start_time' => ['nullable', 'required_if:hours.*.is_off,false', 'date_format:H:i'],
            'hours.*.end_time' => ['nullable', 'required_if:hours.*.is_off,false', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('hours', []) as $index => $row) {
                if (($row['is_off'] ?? false) === false
                    && ! empty($row['start_time']) && ! empty($row['end_time'])
                    && $row['end_time'] <= $row['start_time']) {
                    $validator->errors()->add("hours.$index.end_time", 'The end time must be after the start time.');
                }
            }
        });
    }
}
