<?php

namespace App\Http\Requests\Bulletin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBulletinPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trim the link without changing its path, because URLs are case-sensitive
     * after the host.
     */
    protected function prepareForValidation(): void
    {
        $link = $this->input('link_url');

        if (is_string($link)) {
            $link = trim($link);
            $this->merge(['link_url' => $link === '' ? null : $link]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:2000'],
            'link_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => 'Write something on your note first.',
            'content.max' => 'Notes can be at most 2,000 characters.',
            'link_url.url' => 'The link must be a full http or https URL.',
        ];
    }
}
