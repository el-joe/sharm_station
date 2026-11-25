<?php

namespace App\Services;

use App\DTOs\Auth\ResetPasswordData;
use App\Enums\NotificationEvent;
use App\Exceptions\DomainValidationException;
use App\Models\NotificationLog;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Support\Str;

/**
 * Website passwords chosen from an e-mailed link: the "Welcome, set your password" e-mail of customer accounts
 * created by staff (broker "welcome", 7 days) and "forgot password" (broker "users", 60 minutes). Both links open
 * {FRONTEND_URL}/{locale}/reset-password?token=&email=, which posts to /api/v1/auth/reset-password.
 */
class PasswordResetService
{
    /**
     * Brokers whose tokens the reset page accepts, tried in order.
     */
    private const BROKERS = ['users', 'welcome'];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly NotificationService $notifications,
        private readonly PasswordBrokerFactory $passwords,
    ) {}

    /**
     * Welcome e-mail with the set-password link to an account just created by staff.
     */
    public function sendWelcome(User $user): ?NotificationLog
    {
        return $this->notifications->account(NotificationEvent::CustomerWelcome, $user);
    }

    /**
     * "Forgot password": e-mails a reset link to an active website account. Unknown, inactive and staff addresses
     * get no e-mail and the same answer, so the endpoint does not reveal which e-mails have an account.
     */
    public function sendResetLink(string $email): void
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));

        if ($user === null || ! $user->isActive() || $user->isStaff()) {
            return;
        }

        if ($this->broker('users')->getRepository()->recentlyCreatedToken($user)) {
            return;
        }

        $this->notifications->account(NotificationEvent::PasswordReset, $user);
    }

    /**
     * Sets the new password from a welcome or reset token and signs the account out of every device.
     *
     * @throws DomainValidationException when the link is invalid or expired
     */
    public function reset(ResetPasswordData $data): User
    {
        foreach (self::BROKERS as $name) {
            $updated = null;
            $status = $this->broker($name)->reset($data->credentials(), function (User $user, string $password) use (&$updated): void {
                /** @var User $updated */
                $updated = $this->users->update($user, ['password' => $password]);
                $updated->forceFill(['remember_token' => Str::random(60), 'email_verified_at' => $updated->email_verified_at ?? now()])->save();
                $updated->tokens()->delete();
            });

            if ($status === PasswordBroker::PASSWORD_RESET && $updated instanceof User) {
                return $updated;
            }
        }

        throw DomainValidationException::withErrors(['token' => [__('account.invalid_password_link')]]);
    }

    private function broker(string $name): \Illuminate\Auth\Passwords\PasswordBroker
    {
        /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
        $broker = $this->passwords->broker($name);

        return $broker;
    }
}
