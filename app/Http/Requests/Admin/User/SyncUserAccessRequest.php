<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\User\Concerns\AccessRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncUserAccessRequest extends FormRequest
{
    use AccessRules;

    public function authorize(): bool
    {
        /** @var User $target */
        $target = $this->route('user');

        return ($this->has('roles') || $this->has('permissions')) && $this->canChangeSubmittedAccess($target);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->accessRules();
    }
}
