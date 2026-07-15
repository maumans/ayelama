<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): RedirectResponse|Response
    {
        $user = $this->pendingUser($request);
        if (!$user) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/VerifyOtp', [
            'email'            => Str::mask($user->email, '*', 2, strpos($user->email, '@') - 2),
            'expiresInSeconds' => $this->expiresInSeconds($user),
            'status'           => session('status'),
        ]);
    }

    public function store(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (!$user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'code'            => ['required', 'string', 'size:6'],
            'remember_device' => ['sometimes', 'boolean'],
        ]);

        if (!$twoFactor->verify($user, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => ['Code invalide ou expiré.'],
            ]);
        }

        if ($request->boolean('remember_device')) {
            $twoFactor->rememberDevice($user, $request);
        }

        Auth::login($user, (bool) $request->session()->get('otp.remember', false));
        $request->session()->forget(['otp.user.id', 'otp.remember']);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (!$user) {
            return redirect()->route('login');
        }

        $twoFactor->resend($user, $request->ip());

        return back()->with('status', 'otp-resent');
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('otp.user.id');

        return $userId ? User::find($userId) : null;
    }

    private function expiresInSeconds(User $user): int
    {
        $otp = $user->otpCodes()->whereNull('consumed_at')->latest('id')->first();

        if (!$otp || $otp->expires_at->isPast()) {
            return 0;
        }

        return (int) now()->diffInSeconds($otp->expires_at);
    }
}
