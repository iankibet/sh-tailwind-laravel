<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp delivery channel. Wired into the notification system but intentionally
 * inert for now — it logs the message instead of dispatching it to a provider.
 * Swap the Log call for a real gateway (e.g. WhatsApp Cloud API) later.
 */
class WhatsappChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsapp')) {
            return;
        }

        $message = $notification->toWhatsapp($notifiable);

        if (! $message) {
            return;
        }

        Log::debug('WhatsApp notification (not sent — channel is a no-op)', [
            'to' => $notifiable->phone ?? null,
            'message' => $message,
        ]);
    }
}
