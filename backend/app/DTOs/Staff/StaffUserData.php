<?php

namespace App\DTOs\Staff;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

final readonly class StaffUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public UserRole $role,
        public bool $isActive = true,
        public ?string $password = null,
        public ?string $phone = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            name: trim((string) $request->validated('name')),
            email: strtolower(trim((string) $request->validated('email'))),
            role: UserRole::from($request->validated('role')),
            isActive: (bool) $request->validated('is_active', false),
            password: filled($request->validated('password')) ? (string) $request->validated('password') : null,
            phone: $request->validated('phone'),
        );
    }
}
