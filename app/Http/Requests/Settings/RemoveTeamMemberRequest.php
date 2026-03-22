<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RemoveTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->user()->currentTeam();

        if (! $this->user()->isOwnerOf($team)) {
            return false;
        }

        return (int) $this->input('user_id') !== $this->user()->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
