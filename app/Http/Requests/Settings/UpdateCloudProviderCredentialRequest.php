<?php

namespace App\Http\Requests\Settings;

use App\Enums\CloudProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCloudProviderCredentialRequest extends FormRequest
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
            'provider' => ['required', Rule::enum(CloudProvider::class)],
            'api_key' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
