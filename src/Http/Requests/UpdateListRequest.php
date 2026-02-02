<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateListRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['boolean'],
            'is_default' => ['boolean'],
            'double_opt_in' => ['boolean'],
            'welcome_email_enabled' => ['boolean'],
            'welcome_email_subject' => ['nullable', 'required_if:welcome_email_enabled,true', 'string', 'max:255'],
            'welcome_email_content' => ['nullable', 'required_if:welcome_email_enabled,true', 'string'],
        ];
    }
}
