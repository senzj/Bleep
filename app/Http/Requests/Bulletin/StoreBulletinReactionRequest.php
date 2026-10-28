<?php

namespace App\Http\Requests\Bulletin;

use App\Enums\BulletinReactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulletinReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reaction_type' => ['required', 'string', Rule::enum(BulletinReactionType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reaction_type.required' => 'Choose a reaction first.',
            'reaction_type.enum' => 'That reaction is not available.',
        ];
    }
}
