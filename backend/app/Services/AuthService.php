<?php

namespace App\Services;

use App\DTOs\Auth\LoginData;
use App\DTOs\Auth\RegisterData;
use App\Enums\UserRole;
use App\Exceptions\DomainValidationException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum token authentication for the Next.js site, and session authentication for the admin panel.
 */
class AuthService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * @return array{user: User, token: string}
     */
    public function register(RegisterData $data): array
    {
        $user = $this->users->create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
            'phone' => $data->phone,
            'locale' => $data->locale,
            'currency' => $data->currency,
            'role' => UserRole::Customer,
        ]);

        return ['user' => $user, 'token' => $user->createToken($data->deviceName)->plainTextToken];
    }

    /**
     * @return array{user: User, token: string}
     *
     * @throws DomainValidationException
     */
    public function login(LoginData $data): array
    {
        $user = $this->users->findByEmail($data->email);

        if ($user === null || ! Hash::check($data->password, $user->password)) {
            throw DomainValidationException::withErrors(['email' => ['These credentials do not match our records.']]);
        }

        if (! $user->isActive()) {
            throw DomainValidationException::withErrors(['email' => ['This account has been deactivated. Contact us for help.']]);
        }

        return ['user' => $user, 'token' => $user->createToken($data->deviceName)->plainTextToken];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    /**
     * Signs a staff member into the admin panel (session guard "web").
     *
     * @throws DomainValidationException when the credentials are wrong or the account is not staff.
     */
    public function attemptStaffLogin(LoginData $data, bool $remember = false): User
    {
        $user = $this->users->findByEmail($data->email);

        if ($user === null || ! Hash::check($data->password, $user->password)) {
            throw DomainValidationException::withErrors(['email' => ['These credentials do not match our records.']]);
        }

        if (! $user->isStaff()) {
            throw DomainValidationException::withErrors(['email' => ['This account has no access to the back office.']]);
        }

        if (! $user->isActive()) {
            throw DomainValidationException::withErrors(['email' => ['This account has been deactivated.']]);
        }

        Auth::guard('web')->login($user, $remember);

        return $user;
    }

    public function logoutStaff(): void
    {
        Auth::guard('web')->logout();
    }
}
