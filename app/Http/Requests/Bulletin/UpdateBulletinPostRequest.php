<?php

namespace App\Http\Requests\Bulletin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBulletinPostRequest extends FormRequest
{
    /**
     * Note sizes that still work on a four-row board.
     *
     * @var list<string>
     */
    private const SIZES = ['1x1', '1x2', '1x3', '1x4', '2x1', '2x2', '2x3', '2x4'];

    /**
     * @var list<int>
     */
    private const WIDTHS = [1, 2];

    /**
     * @var list<int>
     */
    private const HEIGHTS = [1, 2, 3, 4];

    /**
     * @var list<string>
     */
    private const TEXTURES = [
        'plain',
        'torn',
        'folded',
        'crumpled',
        'scratched',
        'perforated',
        'scraps',
        'notebook',
        'torn-edges',
        'zigzag',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trim the link without changing its path, because URLs are case-sensitive
     * after the host. Accept either a single "size" field or explicit
     * width/height fields, mirroring the store request.
     */
    protected function prepareForValidation(): void
    {
        $link = $this->input('link_url');

        if (is_string($link)) {
            $link = trim($link);
            $this->merge(['link_url' => $link === '' ? null : $link]);
        }

        if (is_string($this->input('size'))) {
            $size = strtolower(trim($this->input('size')));
            $parts = explode('x', $size);

            $this->merge(['size' => $size]);

            if (count($parts) === 2) {
                $this->merge([
                    'width' => (int) $parts[0],
                    'height' => (int) $parts[1],
                ]);
            }
        }

        foreach (['paper_color', 'paper_texture'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = strtolower(trim($value));
                $this->merge([$field => $value === '' ? null : $value]);
            }
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
            'media' => [
                'nullable',
                'file',
                'max:51200',
                config('media.mimes_rule'),
            ],
            'remove_media' => ['nullable', 'boolean'],
            'size' => ['nullable', Rule::in(self::SIZES)],
            'width' => ['nullable', 'integer', Rule::in(self::WIDTHS)],
            'height' => ['nullable', 'integer', Rule::in(self::HEIGHTS)],
            'paper_color' => ['nullable', 'string', 'regex:/^#[0-9a-f]{6}$/'],
            'paper_texture' => ['nullable', 'string', Rule::in(self::TEXTURES)],
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
            'media.file' => 'The attachment could not be uploaded. Please try again.',
            'media.max' => 'Attachments must be 50MB or smaller.',
            'media.mimetypes' => 'Attach an image, GIF, video, or audio file.',
            'size.in' => 'Choose one of the available note sizes.',
            'width.in' => 'Choose one of the available note sizes.',
            'height.in' => 'Choose one of the available note sizes.',
            'paper_color.regex' => 'Choose a valid paper colour.',
            'paper_texture.in' => 'Choose one of the available paper textures.',
        ];
    }
}
