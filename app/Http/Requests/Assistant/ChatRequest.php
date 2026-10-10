<?php

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'context' => ['nullable', 'array'],
            'context.route' => ['nullable', 'string', 'max:300'],
            'context.url' => ['nullable', 'string', 'max:500'],
            'context.title' => ['nullable', 'string', 'max:200'],
            'context.errors' => ['nullable', 'array', 'max:10'],
            'context.errors.*' => ['string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Type a message first.',
            'message.max' => 'Please keep the message under 2000 characters.',
        ];
    }
}
