<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ComparePokemonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'left' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9 -]+$/'],
            'right' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9 -]+$/'],
        ];
    }
}
