<?php

namespace MoMagdy\TelegramSupport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendTelegramMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message'  => 'required|string|max:4096',
            'chat_id'  => 'nullable|string',
            'type'     => 'nullable|in:text,photo,buttons',
            'photo'    => 'required_if:type,photo|nullable|string',
        ];
    }
}
