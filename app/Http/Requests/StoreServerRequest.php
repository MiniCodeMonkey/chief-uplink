<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreServerRequest extends FormRequest
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
            'credential_id' => ['required', 'integer', 'exists:cloud_provider_credentials,id'],
            'region_id' => ['required', 'string', 'max:255'],
            'size_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'ssh_key_id' => ['required', 'integer', 'exists:ssh_keys,id'],
        ];
    }
}
