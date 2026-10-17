<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\User\Concerns\AccessRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    use AccessRules;

    public function authorize(): bool
    {
        return ($this->user()?->can('create', User::class) ?? false)
            && $this->canChangeSubmittedAccess(new User);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            ...$this->accessRules(),
        ];
    }
}
