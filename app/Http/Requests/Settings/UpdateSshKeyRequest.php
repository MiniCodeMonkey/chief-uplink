<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSshKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isOwnerOf($this->user()->currentTeam());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'public_key' => ['required', 'string', 'max:4096'],
        ];
    }
}
