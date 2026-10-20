<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Dev\DevLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signs in as the chosen user without a password or 2FA. Returns 404 outside
 * the local environment or when DEV_LOGIN is off.
 */
class DevLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(DevLogin::enabled(), 404);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $user = User::query()->where('is_active', true)->whereKey($validated['user_id'])->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }
}
