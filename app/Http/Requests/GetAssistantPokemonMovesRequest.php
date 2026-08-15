<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetAssistantPokemonMovesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('assistant_user');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pokemon' => ['required'],
            'learn_method' => ['nullable', Rule::in(['level-up', 'machine', 'tutor', 'egg'])],
            'version_group' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}
