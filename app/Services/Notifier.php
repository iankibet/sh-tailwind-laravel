<?php

namespace App\Services;

use App\Notifications\GeneralNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Single entry point for sending a notification by slug.
 *
 *   Notifier::send('welcome', $user, ['name' => 'Pius']);
 *
 * Resolves the definition (DB override or config default), substitutes the
 * given {placeholder} values across every text field, then dispatches the
 * notification across the definition's channels.
 */
class Notifier
{
    /**
     * @param  mixed  $notifiables  a notifiable model, a collection, or an array of them
     * @param  array<string, mixed>  $placeholders
     */
    public static function send(string $slug, mixed $notifiables, array $placeholders = []): void
    {
        $definition = NotificationDefinitions::resolve($slug);

        if ($definition === null) {
            throw new \InvalidArgumentException("Unknown notification definition [{$slug}].");
        }

        Notification::send(
            $notifiables,
            new GeneralNotification(static::render($definition, $placeholders)),
        );
    }

    /**
     * Replace {key} tokens in every text field of the definition.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $placeholders
     * @return array<string, mixed>
     */
    private static function render(array $definition, array $placeholders): array
    {
        $tokens = [];
        foreach ($placeholders as $key => $value) {
            $tokens['{'.$key.'}'] = (string) $value;
        }

        foreach (['subject', 'mail', 'sms', 'whatsapp', 'action_label', 'action_url'] as $field) {
            if (is_string($definition[$field] ?? null)) {
                $definition[$field] = strtr($definition[$field], $tokens);
            }
        }

        return $definition;
    }
}
