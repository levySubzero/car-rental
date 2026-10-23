<?php

namespace App\Notifications\Booking;

use App\Models\Booking;
use App\Notifications\Channels\WhatsAppChannel;
use App\Settings\NotificationSettings;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A booking notification (M10, TECHNICAL_PLAN §13). Queued, encrypted on the queue (renter
 * mails carry the plain booking token), and sent on the channels Notification settings turn
 * on for its event, where the recipient has an address for that channel.
 */
abstract class BookingNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Booking $booking)
    {
        $this->afterCommit();
    }

    /**
     * The key in NotificationSettings::$channels.
     */
    abstract public function event(): string;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $settings = app(NotificationSettings::class);
        $channels = [];

        if ($settings->enabled($this->event(), 'mail') && $this->routeFor($notifiable, 'mail')) {
            $channels[] = 'mail';
        }

        if ($settings->enabled($this->event(), 'whatsapp') && method_exists($this, 'toWhatsApp') && $this->routeFor($notifiable, 'whatsapp')) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    protected function dates(): string
    {
        $zone = (string) config('baines.timezone');

        return $this->booking->pickup_at->setTimezone($zone)->format('D j M Y, H:i')
            .' to '.$this->booking->return_at->setTimezone($zone)->format('D j M Y, H:i');
    }

    protected function money(?int $minor): string
    {
        return (new Money((int) $minor))->format();
    }

    protected function renterName(): string
    {
        $driver = $this->booking->driver;

        return $driver === null ? 'The renter' : trim("{$driver->first_name} {$driver->surname}");
    }

    /**
     * The recipient's WhatsApp number.
     */
    protected function phoneFor(object $notifiable): ?string
    {
        $route = $this->routeFor($notifiable, 'whatsapp');

        return is_string($route) ? $route : null;
    }

    /**
     * The recipient's address on a channel: an email (or list of them), or a phone number
     * for `whatsapp`.
     *
     * @return string|list<string>|null
     */
    protected function routeFor(object $notifiable, string $channel): string|array|null
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $route = $notifiable->routeNotificationFor($channel, $this);

        return filled($route) ? $route : null;
    }
}
