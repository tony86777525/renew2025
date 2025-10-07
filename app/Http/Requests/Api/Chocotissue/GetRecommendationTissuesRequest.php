<?php

namespace App\Http\Requests\Api\Chocotissue;

use Illuminate\Foundation\Http\FormRequest;

class GetRecommendationTissuesRequest extends FormRequest
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
            'is_pc'      => ['required', 'boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // 在驗證前，將 'true'/'false' 字串轉換為布林值
        $this->merge([
            'is_pc' => filter_var($this->is_pc, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        ]);
    }
}