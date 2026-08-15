<?php

namespace App\Http\Requests;

use App\Enums\AssistantActionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAssistantActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->attributes->has('assistant_user');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AssistantActionType::class)],
            'pokemon' => ['required'],
            'changes' => [
                Rule::requiredIf($this->input('type') === AssistantActionType::UpdatePokemon->value),
                Rule::prohibitedIf($this->input('type') !== AssistantActionType::UpdatePokemon->value),
                'array:nickname,notes,is_favorite',
            ],
            'changes.nickname' => ['sometimes', 'nullable', 'string', 'max:60'],
            'changes.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'changes.is_favorite' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('type') !== AssistantActionType::UpdatePokemon->value) {
                return;
            }

            $changes = $this->input('changes', []);
            $allowedFields = ['nickname', 'notes', 'is_favorite'];

            if (! is_array($changes) || ! collect($allowedFields)->contains(
                fn (string $field): bool => array_key_exists($field, $changes),
            )) {
                $validator->errors()->add('changes', 'Indica al menos un cambio para el Pokémon.');
            }
        }];
    }
}
