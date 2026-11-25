<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TwoFactorChallengeRequest;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AuthService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Second login step for accounts with TOTP enabled: an authenticator code or a one-time recovery code.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly AuthService $auth,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        return $this->pendingUser($request) === null
            ? redirect()->route('admin.login')->withErrors(['email' => 'Sign in again.'])
            : view('admin.auth.two-factor-challenge');
    }

    public function store(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('admin.login')->withErrors(['email' => 'The sign-in has expired. Sign in again.']);
        }

        if (! $this->twoFactor->verify($user, $request->validated('code'), $request->validated('recovery_code'))) {
            return back()->withErrors([$request->filled('recovery_code') ? 'recovery_code' : 'code' => 'The code is not valid.']);
        }

        $remember = (bool) ($request->session()->get(LoginController::CHALLENGE_SESSION_KEY)['remember'] ?? false);
        $request->session()->forget(LoginController::CHALLENGE_SESSION_KEY);
        $this->auth->loginStaff($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(LoginController::CHALLENGE_SESSION_KEY);

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $user = $this->users->find((int) $pending['user_id']);

        return $user !== null && $user->isStaff() && $user->isActive() && $user->hasTwoFactorEnabled() ? $user : null;
    }
}
