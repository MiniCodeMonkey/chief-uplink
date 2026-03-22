<?php

namespace App\Http\Requests\Api;

use App\Enums\CommandType;
use App\Models\Device;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        $device = $this->route('device');

        if (! $device instanceof Device) {
            return false;
        }

        return $this->user()->isMemberOf($device->team);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(CommandType::class)],
            'payload' => ['sometimes', 'array'],
        ];
    }
}
