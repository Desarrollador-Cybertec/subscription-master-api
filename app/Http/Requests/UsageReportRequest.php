<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UsageReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'installation_id' => 'sometimes|uuid',
            'product' => 'sometimes|string|in:sintyc,chronology',
            'metric' => 'required|string|max:100',
            'value' => 'required|integer|min:1',
            'reference_id' => 'nullable|string|max:255',
        ];
    }
}
