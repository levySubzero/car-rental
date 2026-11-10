<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Integrations\SendTestMailRequest;
use App\Models\Booking;
use App\Services\Notifications\TestMailService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Test emails to a real inbox from the Resend integration page (M12B.14).
 */
class IntegrationTestMailController extends Controller
{
    public function __invoke(SendTestMailRequest $request, TestMailService $mail): RedirectResponse
    {
        $reference = $request->validated('reference');
        $booking = filled($reference) ? Booking::query()->where('reference', $reference)->firstOrFail() : null;
        $result = $mail->send((string) $request->validated('email'), $booking, $request->user());

        $message = collect([
            $result['sent'] === [] ? null : 'Sent: '.implode(', ', $result['sent']).'.',
            $result['skipped'] === [] ? null : 'Skipped (turned off, or the booking has no data for it): '.implode(', ', $result['skipped']).'.',
            $result['failed'] === [] ? null : 'Failed: '.implode(', ', $result['failed']).". {$result['error']}",
        ])->filter()->implode(' ');

        Inertia::flash('toast', [
            'type' => $result['failed'] === [] && $result['sent'] !== [] ? 'success' : 'error',
            'message' => $message ?: 'Nothing was sent.',
        ]);

        return back();
    }
}
