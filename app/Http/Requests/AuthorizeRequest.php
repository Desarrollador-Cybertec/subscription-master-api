<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthorizeRequest extends FormRequest
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
            'action' => 'required|string|max:100',
            'quantity' => 'sometimes|integer|min:1',
            'consume' => 'sometimes|boolean',
            'reference_id' => 'sometimes|string|max:255',
        ];
    }
}
