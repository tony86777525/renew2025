<?php

namespace App\Http\Requests\Api\Chocotissue;

use Illuminate\Foundation\Http\FormRequest;

class GetUserRankingTissuesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'load_times' => ['required', 'integer', 'min:1'],
            'pref_id'    => ['nullable', 'integer', 'min:1'],
        ];
    }
}
