<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UnsubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:subscribers,email'],
            'list_id' => ['nullable', 'exists:subscription_lists,id'],
        ];
    }
}
