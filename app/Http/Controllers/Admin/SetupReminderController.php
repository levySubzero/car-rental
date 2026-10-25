<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The before-launch popup on the dashboard (M11B.10, D43) stays closed for the rest of the
 * session once a staff member closes it. Logging out ends the session, so it opens again on
 * the next sign-in. Each staff member's own session: no permission needed.
 */
class SetupReminderController extends Controller
{
    public const SESSION_KEY = 'setup_reminder_dismissed';

    public function __invoke(Request $request): RedirectResponse
    {
        $request->session()->put(self::SESSION_KEY, true);

        return back();
    }
}
