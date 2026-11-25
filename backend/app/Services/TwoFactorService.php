<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\UserRole;
use App\Exceptions\DomainValidationException;
use App\Models\User;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor authentication for the back office (SRS 14.2): enrolment with a QR code, one-time recovery
 * codes (stored hashed), replay protection (a code is accepted once) and the "require for super admins" setting.
 */
class TwoFactorService
{
    public const RECOVERY_CODES = 8;

    public const WINDOW = 1;

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly UserRepositoryInterface $users,
        private readonly SettingRepositoryInterface $settings,
        private readonly ActivityLogger $activity,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * QR code (inline SVG) of the otpauth:// URL for authenticator apps.
     */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl((string) $this->settings->get('site.name', config('app.name')), $user->email, $secret);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($url);

        return trim(substr($svg, (int) strpos($svg, '<svg')));
    }

    /**
     * Confirms enrolment with a first valid code and returns the plain recovery codes (shown once).
     *
     * @return list<string>
     *
     * @throws DomainValidationException
     */
    public function enable(User $user, string $secret, string $code): array
    {
        $timestep = $this->google2fa->verifyKeyNewer($secret, $this->normalise($code), 0, self::WINDOW);

        if ($timestep === false) {
            throw DomainValidationException::withErrors(['code' => ['The code is not valid. Check the time on your phone and try again.']]);
        }

        $codes = $this->newRecoveryCodes();
        $this->users->update($user, [
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $this->hashCodes($codes),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_timestep' => (int) $timestep,
        ]);
        $this->activity->log(ActivityAction::TwoFactorEnabled, $user, "Two-factor authentication enabled for {$user->email}", causer: $user);

        return $codes;
    }

    /**
     * Login challenge: a current TOTP code (each accepted once) or an unused recovery code (then consumed).
     */
    public function verify(User $user, ?string $code, ?string $recoveryCode = null): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }

        if (filled($code)) {
            $timestep = $this->google2fa->verifyKeyNewer((string) $user->two_factor_secret, $this->normalise((string) $code), (int) ($user->two_factor_last_timestep ?? 0), self::WINDOW);

            if ($timestep === false) {
                return false;
            }

            $this->users->update($user, ['two_factor_last_timestep' => (int) $timestep]);

            return true;
        }

        if (filled($recoveryCode)) {
            $remaining = (array) ($user->two_factor_recovery_codes ?? []);

            foreach ($remaining as $index => $hash) {
                if (Hash::check(strtoupper(trim((string) $recoveryCode)), (string) $hash)) {
                    unset($remaining[$index]);
                    $this->users->update($user, ['two_factor_recovery_codes' => array_values($remaining)]);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->newRecoveryCodes();
        $this->users->update($user, ['two_factor_recovery_codes' => $this->hashCodes($codes)]);

        return $codes;
    }

    /**
     * Turns 2FA off: by the user (after a valid code) or a reset by another super admin.
     */
    public function disable(User $user, User $actor): void
    {
        $this->users->update($user, [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestep' => null,
        ]);

        $description = $actor->is($user) ? "Two-factor authentication disabled by {$user->email}" : "Two-factor authentication of {$user->email} reset by {$actor->email}";
        $this->activity->log(ActivityAction::TwoFactorDisabled, $user, $description, causer: $actor);
    }

    /**
     * Super admins must enrol when the "security.require_two_factor" setting is on.
     */
    public function isRequiredFor(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin && (bool) $this->settings->get('security.require_two_factor', false);
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count((array) ($user->two_factor_recovery_codes ?? []));
    }

    /**
     * @return list<string>
     */
    private function newRecoveryCodes(): array
    {
        return array_map(fn (): string => strtoupper(Str::random(5).'-'.Str::random(5)), range(1, self::RECOVERY_CODES));
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function hashCodes(array $codes): array
    {
        return array_map(fn (string $code): string => Hash::make($code), $codes);
    }

    private function normalise(string $code): string
    {
        return preg_replace('/\D/', '', $code) ?? '';
    }
}
