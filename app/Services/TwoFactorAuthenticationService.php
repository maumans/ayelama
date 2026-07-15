<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Models\UserOtpCode;
use App\Models\UserTrustedDevice;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TwoFactorAuthenticationService
{
    private const MAX_ATTEMPTS = 5;
    private const TRUSTED_DEVICE_COOKIE = 'trusted_device';
    private const TRUSTED_DEVICE_DAYS = 30;

    public function durationMinutes(): int
    {
        return (int) Setting::get('otp_duration_minutes', 10);
    }

    public function generateAndSend(User $user, ?string $ip = null): UserOtpCode
    {
        $user->otpCodes()->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $validityMinutes = $this->durationMinutes();

        $otp = $user->otpCodes()->create([
            'code_hash'    => hash('sha256', $code),
            'expires_at'   => now()->addMinutes($validityMinutes),
            'last_sent_at' => now(),
            'ip_address'   => $ip,
        ]);

        $user->notify(new TwoFactorCodeNotification($code, $validityMinutes));

        return $otp;
    }

    public function verify(User $user, string $code): bool
    {
        $otp = $user->otpCodes()
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (!$otp || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (!hash_equals($otp->code_hash, hash('sha256', $code))) {
            $otp->increment('attempts');
            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }

    public function resend(User $user, ?string $ip = null): void
    {
        $key = "otp-resend:{$user->id}";

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'code' => ["Trop de renvois. Réessayez dans {$seconds} secondes."],
            ]);
        }

        $lastSent = $user->otpCodes()->latest('id')->value('last_sent_at');
        if ($lastSent && now()->diffInSeconds($lastSent) < 60) {
            return;
        }

        RateLimiter::hit($key, 600);

        $this->generateAndSend($user, $ip);
    }

    public function rememberDevice(User $user, Request $request): void
    {
        $token = Str::random(64);

        $user->trustedDevices()->create([
            'token_hash'   => hash('sha256', $token),
            'label'        => Str::limit($request->userAgent() ?? 'Appareil inconnu', 120),
            'ip_address'   => $request->ip(),
            'last_used_at' => now(),
            'expires_at'   => now()->addDays(self::TRUSTED_DEVICE_DAYS),
        ]);

        Cookie::queue(
            self::TRUSTED_DEVICE_COOKIE,
            $token,
            60 * 24 * self::TRUSTED_DEVICE_DAYS,
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    public function isDeviceTrusted(User $user, Request $request): bool
    {
        $token = $request->cookie(self::TRUSTED_DEVICE_COOKIE);
        if (!$token) {
            return false;
        }

        $device = $user->trustedDevices()->actif()->where('token_hash', hash('sha256', $token))->first();
        if (!$device) {
            return false;
        }

        $device->update(['last_used_at' => now()]);

        return true;
    }
}
