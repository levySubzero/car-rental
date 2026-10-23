<?php

namespace App\Notifications;

use App\Models\BookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the staff emails a renter sent a request from the portal (M10B).
 */
class NewBookingRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly BookingRequest $bookingRequest)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->bookingRequest->loadMissing('booking.car');
        $reference = $request->booking->reference;

        return (new MailMessage)
            ->subject("{$request->type->label()} request for {$reference}")
            ->greeting("{$request->type->label()} request")
            ->line("**Booking:** {$reference} ({$request->booking->car->name})")
            ->line($request->message)
            ->action('Open the requests', route('admin.booking-requests.index'));
    }
}
