<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Notifications\AdminAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The bell's actions (M10B). Alerts are always looked up among the signed-in user's own,
 * so one staff member can never open or clear another's.
 */
class AlertController extends Controller
{
    public function __construct(private readonly AdminAlerts $alerts) {}

    public function open(Request $request, string $alert): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return redirect()->to($this->alerts->open($user, $alert));
    }

    public function readAll(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->alerts->markAllRead($user);

        return back();
    }
}
