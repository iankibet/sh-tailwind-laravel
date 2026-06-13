<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * SMS delivery channel. Wired into the notification system but intentionally
 * inert for now — it logs the message instead of dispatching it to a provider.
 * Swap the Log call for a real gateway (e.g. Twilio/Africa's Talking) later.
 */
class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);

        if (! $message) {
            return;
        }

        Log::debug('SMS notification (not sent — channel is a no-op)', [
            'to' => $notifiable->phone ?? null,
            'message' => $message,
        ]);
    }
}
