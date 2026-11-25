<?php

namespace App\Http\Controllers\Admin\Auth;

use App\DTOs\Auth\LoginData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public const CHALLENGE_SESSION_KEY = 'admin.two_factor_login';

    public const CHALLENGE_MINUTES = 10;

    public function __construct(private readonly AuthService $auth) {}

    public function create(): View
    {
        return view('admin.auth.login');
    }

    /**
     * Password step. Accounts with two-factor authentication continue to the code challenge before being signed in.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $this->auth->validateStaffCredentials(LoginData::fromRequest($request));
        $request->session()->regenerate();

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(self::CHALLENGE_SESSION_KEY, [
                'user_id' => $user->id,
                'remember' => $request->boolean('remember'),
                'expires_at' => now()->addMinutes(self::CHALLENGE_MINUTES)->getTimestamp(),
            ]);

            return redirect()->route('admin.two-factor.challenge');
        }

        $this->auth->loginStaff($user, $request->boolean('remember'));

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->auth->logoutStaff();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
