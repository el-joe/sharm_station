<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TwoFactorCodeRequest;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Two-factor authentication of the signed-in staff member (enrol with QR, recovery codes, disable),
 * and the reset of another account by a super admin.
 */
class TwoFactorController extends Controller
{
    public const SETUP_SESSION_KEY = 'admin.two_factor_setup';

    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $secret = null;
        $qrCode = null;

        if (! $user->hasTwoFactorEnabled()) {
            $secret = $request->session()->get(self::SETUP_SESSION_KEY) ?? $this->twoFactor->generateSecret();
            $request->session()->put(self::SETUP_SESSION_KEY, $secret);
            $qrCode = $this->twoFactor->qrCodeSvg($user, $secret);
        }

        return view('admin.security.two-factor', [
            'user' => $user,
            'secret' => $secret,
            'qrCode' => $qrCode,
            'required' => $this->twoFactor->isRequiredFor($user),
            'remainingCodes' => $this->twoFactor->remainingRecoveryCodes($user),
            'recoveryCodes' => $request->session()->get('two_factor_recovery_codes'),
        ]);
    }

    public function enable(TwoFactorCodeRequest $request): RedirectResponse
    {
        $secret = $request->session()->get(self::SETUP_SESSION_KEY);
        abort_if(! is_string($secret), 409, 'Open the setup page again.');

        $codes = $this->twoFactor->enable($request->user(), $secret, $request->validated('code'));
        $request->session()->forget(self::SETUP_SESSION_KEY);

        return redirect()->route('admin.two-factor.show')
            ->with('two_factor_recovery_codes', $codes)
            ->with('success', 'Two-factor authentication is on. Store the recovery codes in a safe place.');
    }

    public function regenerate(TwoFactorCodeRequest $request): RedirectResponse
    {
        $this->ensureValidCode($request);

        return redirect()->route('admin.two-factor.show')
            ->with('two_factor_recovery_codes', $this->twoFactor->regenerateRecoveryCodes($request->user()))
            ->with('success', 'New recovery codes generated; the old ones no longer work.');
    }

    public function disable(TwoFactorCodeRequest $request): RedirectResponse
    {
        abort_if($this->twoFactor->isRequiredFor($request->user()), 403, 'Two-factor authentication is required for super admins.');
        $this->ensureValidCode($request);
        $this->twoFactor->disable($request->user(), $request->user());

        return redirect()->route('admin.two-factor.show')->with('success', 'Two-factor authentication is off.');
    }

    /**
     * Super admin resets another staff member's 2FA (lost phone): they enrol again at their next sign-in.
     */
    public function reset(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'Use your own security page to change your two-factor settings.');
        $this->twoFactor->disable($user, $request->user());

        return back()->with('success', "Two-factor authentication of {$user->name} has been reset.");
    }

    private function ensureValidCode(TwoFactorCodeRequest $request): void
    {
        if (! $this->twoFactor->verify($request->user(), $request->validated('code'))) {
            throw DomainValidationException::withErrors(['code' => ['The code is not valid.']]);
        }
    }
}
