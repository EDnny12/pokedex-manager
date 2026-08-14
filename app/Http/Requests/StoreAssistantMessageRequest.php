<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\File;

class StoreAssistantMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('assistantConversation')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'required_without:images', 'string', 'max:2000'],
            'client_message_id' => ['required', 'uuid'],
            'images' => ['nullable', 'required_without:message', 'array', 'max:2'],
            'images.*' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->extensions(['jpg', 'jpeg', 'png', 'webp'])
                    ->max('5mb')
                    ->dimensions((new Dimensions)->maxWidth(4096)->maxHeight(4096)),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'message.required_without' => 'Escribe un mensaje o adjunta al menos una imagen.',
            'message.max' => 'El mensaje no puede superar los 2,000 caracteres.',
            'images.required_without' => 'Escribe un mensaje o adjunta al menos una imagen.',
            'images.max' => 'Puedes adjuntar hasta 2 imágenes por mensaje.',
            'images.*.image' => 'Cada archivo debe ser una imagen válida.',
            'images.*.mimetypes' => 'Solo puedes adjuntar imágenes JPEG, PNG o WebP.',
            'images.*.extensions' => 'Solo puedes adjuntar archivos JPG, JPEG, PNG o WebP.',
            'images.*.max' => 'Cada imagen puede pesar hasta 5 MB.',
            'images.*.dimensions' => 'Cada imagen puede medir hasta 4096 × 4096 píxeles.',
        ];
    }
}
