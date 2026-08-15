<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Pagination\Cursor;
use UnexpectedValueException;

class PaginateAssistantMessagesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('assistantConversation')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cursor' => [
                'nullable',
                'string',
                'max:2048',
                'regex:/^[A-Za-z0-9_-]+$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $cursor = Cursor::fromEncoded($value);

                    try {
                        $parameters = $cursor?->parameters(['created_at', 'id']);
                    } catch (UnexpectedValueException) {
                        $parameters = null;
                    }

                    if (! is_array($parameters)
                        || ! is_string($parameters[0] ?? null)
                        || ! is_string($parameters[1] ?? null)) {
                        $fail('El cursor del historial no es válido.');
                    }
                },
            ],
        ];
    }

    public function paginationCursor(): ?Cursor
    {
        $cursor = $this->validated('cursor');

        return is_string($cursor) ? Cursor::fromEncoded($cursor) : null;
    }
}
