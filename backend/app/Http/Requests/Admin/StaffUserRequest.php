<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-users');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active'), 'email' => strtolower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $creating = ! $user instanceof User;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($creating ? null : $user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role): string => $role->value, UserRole::staff()))],
            'is_active' => ['boolean'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'confirmed', Password::min(10)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['email.unique' => 'An account with this email already exists.'];
    }
}
