<?php

namespace App\Services;

use App\Models\Core\NotificationMessage;
use Illuminate\Support\Arr;

/**
 * Resolves notification definitions. Defaults live in config/notifications.php.
 * The DB stores overrides only: when an admin edits a definition a
 * notification_messages row is created and takes precedence over the default.
 */
class NotificationDefinitions
{
    /** The fields that make up a definition. */
    private const FIELDS = ['subject', 'mail', 'sms', 'whatsapp', 'channels', 'action_label', 'action_url'];

    /**
     * Every definition (config defaults merged with their DB override, if any),
     * each tagged with `slug`, `placeholders` and `is_overridden`. For the admin list.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        $overrides = NotificationMessage::all()->keyBy('slug');

        return collect(static::defaults())
            ->map(function (array $default, string $slug) use ($overrides) {
                $definition = static::merge($default, $overrides->get($slug));
                $definition['slug'] = $slug;
                $definition['placeholders'] = $default['placeholders'] ?? [];
                $definition['is_overridden'] = $overrides->has($slug);

                return $definition;
            })
            ->values()
            ->all();
    }

    /**
     * Resolve a single definition: DB override if present, otherwise the config default.
     *
     * @return array<string, mixed>|null
     */
    public static function resolve(string $slug): ?array
    {
        $default = Arr::get(static::defaults(), $slug);

        if ($default === null) {
            return null;
        }

        $definition = static::merge($default, NotificationMessage::where('slug', $slug)->first());
        $definition['slug'] = $slug;
        $definition['placeholders'] = $default['placeholders'] ?? [];

        return $definition;
    }

    /**
     * The raw, unmodified config default for a slug (used by the admin UI to
     * show what a reset would restore).
     *
     * @return array<string, mixed>|null
     */
    public static function default(string $slug): ?array
    {
        return Arr::get(static::defaults(), $slug);
    }

    /**
     * Create or update the DB override for a slug.
     *
     * @param  array<string, mixed>  $data
     */
    public static function override(string $slug, array $data): NotificationMessage
    {
        return NotificationMessage::updateOrCreate(
            ['slug' => $slug],
            Arr::only($data, static::FIELDS),
        );
    }

    /** Remove the override, falling back to the config default. */
    public static function reset(string $slug): void
    {
        NotificationMessage::where('slug', $slug)->delete();
    }

    /** @return array<string, array<string, mixed>> */
    public static function defaults(): array
    {
        return config('notifications', []);
    }

    /**
     * Overlay an override row's non-null fields onto the config default.
     *
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    private static function merge(array $default, ?NotificationMessage $override): array
    {
        $definition = [];

        foreach (self::FIELDS as $field) {
            $value = $override?->{$field};
            $definition[$field] = $value ?? ($default[$field] ?? null);
        }

        return $definition;
    }
}
