<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic notification driven by a resolved + rendered definition (placeholders
 * already substituted). Dispatched via App\Services\Notifier.
 */
class GeneralNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $definition  slug, subject, mail, sms, whatsapp, channels, action_label, action_url
     */
    public function __construct(public array $definition)
    {
    }

    /**
     * Map the definition's channels onto Laravel notification channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $map = [
            'database' => 'database',
            'mail' => 'mail',
            'sms' => SmsChannel::class,
            'whatsapp' => WhatsappChannel::class,
        ];

        return collect($this->definition['channels'] ?? ['database'])
            ->map(fn ($channel) => $map[$channel] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->definition['subject'] ?? '')
            ->line($this->definition['mail'] ?? '');

        if (! empty($this->definition['action_url']) && ! empty($this->definition['action_label'])) {
            $mail->action($this->definition['action_label'], url($this->definition['action_url']));
        }

        return $mail;
    }

    /**
     * Stored in the `notifications` table and read by the dashboard dropdown.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'slug' => $this->definition['slug'] ?? null,
            'subject' => $this->definition['subject'] ?? '',
            'message' => $this->definition['mail'] ?? '',
            'action_label' => $this->definition['action_label'] ?? null,
            'action_url' => $this->definition['action_url'] ?? null,
        ];
    }

    public function toSms(object $notifiable): ?string
    {
        return $this->definition['sms'] ?? null;
    }

    public function toWhatsapp(object $notifiable): ?string
    {
        return $this->definition['whatsapp'] ?? null;
    }
}
