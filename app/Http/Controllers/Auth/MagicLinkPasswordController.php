<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\NonEmployeeUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class MagicLinkPasswordController extends Controller
{
    /**
     * Show the set-password form. Reached only through a valid signed link
     * (the `signed` middleware validates the token and its expiry).
     */
    public function show(Request $request, NonEmployeeUser $user): View|RedirectResponse
    {
        // A user who already completed setup should not reuse an old link.
        if ($user->password_set_at !== null) {
            return redirect()
                ->route('login')
                ->with('status', 'This link is no longer valid. Please call the admin to reset your password.');
        }

        return view('auth.magic-reset', [
            'user' => $user,
            // The form posts back to this same signed URL so the POST is signed too.
            'action' => $request->fullUrl(),
        ]);
    }

    /**
     * Set the password and mark the account as self-service completed, so the
     * magic link cannot be used again.
     */
    public function update(Request $request, NonEmployeeUser $user): RedirectResponse
    {
        if ($user->password_set_at !== null) {
            return redirect()
                ->route('login')
                ->with('status', 'This link is no longer valid. Please call the admin to reset your password.');
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($request->string('password')),
            'password_set_at' => now(),
            'remember_token' => null,
        ])->save();

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been set. You can now sign in.');
    }
}
