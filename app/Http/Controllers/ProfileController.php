<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\UserTrustedDevice;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'trustedDevices' => $request->user()->trustedDevices()->actif()->orderByDesc('last_used_at')->get()->map(fn ($d) => [
                'id'           => $d->id,
                'label'        => $d->label,
                'ip_address'   => $d->ip_address,
                'last_used_at' => $d->last_used_at?->diffForHumans(),
                'expires_at'   => $d->expires_at->format('d/m/Y'),
            ]),
        ]);
    }

    /**
     * Révoque un appareil de confiance (fin de l'exemption OTP pour cet appareil).
     */
    public function revokeTrustedDevice(Request $request, UserTrustedDevice $trustedDevice): RedirectResponse
    {
        abort_unless($trustedDevice->user_id === $request->user()->id, 403);

        $trustedDevice->delete();

        return back()->with('success', 'Appareil révoqué.');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
