<?php

namespace App\Streams\Admin;

use App\Services\NotificationDefinitions;
use Iankibet\Shbackend\App\Repositories\ShRepository;
use Iankibet\Streamline\Attributes\Permission;
use Iankibet\Streamline\Stream;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

#[Permission('notifications')]
class NotificationsStream extends Stream
{
    private const CHANNELS = ['database', 'mail', 'sms', 'whatsapp'];

    /** @return array{status: string, definitions: array<int, array<string, mixed>>} */
    #[Permission('notifications.list')]
    public function list(): array
    {
        return [
            'status' => 'success',
            'definitions' => NotificationDefinitions::all(),
        ];
    }

    /** @return array{status: string, definition: array<string, mixed>, default: array<string, mixed>|null} */
    #[Permission('notifications.view')]
    public function get(string $slug): array
    {
        $definition = NotificationDefinitions::resolve($slug);
        abort_if($definition === null, 404, 'Notification definition not found.');

        return [
            'status' => 'success',
            'definition' => $definition,
            'default' => NotificationDefinitions::default($slug),
            'is_overridden' => NotificationDefinitions::default($slug) !== $definition,
        ];
    }

    /** @return array{status: string, definition: array<string, mixed>} */
    #[Permission('notifications.update')]
    public function update(?string $slug = null): array
    {
        $slug ??= request()->string('slug');
        abort_if(NotificationDefinitions::default($slug) === null, 404, 'Notification definition not found.');

        $data = Validator::validate(request()->all(), [
            'subject' => ['required', 'string', 'max:255'],
            'mail' => ['required', 'string'],
            'sms' => ['nullable', 'string', 'max:1000'],
            'whatsapp' => ['nullable', 'string', 'max:2000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', Rule::in(self::CHANNELS)],
            'action_label' => ['nullable', 'string', 'max:120'],
            'action_url' => ['nullable', 'string', 'max:255'],
        ]);

        NotificationDefinitions::override($slug, $data);
        ShRepository::storeLog('notification_updated', "Updated the '{$slug}' notification");

        return [
            'status' => 'success',
            'definition' => NotificationDefinitions::resolve($slug),
        ];
    }

    /** @return array{status: string, definition: array<string, mixed>} */
    #[Permission('notifications.update')]
    public function reset(?string $slug = null): array
    {
        $slug ??= request()->string('slug');
        abort_if(NotificationDefinitions::default($slug) === null, 404, 'Notification definition not found.');

        NotificationDefinitions::reset($slug);
        ShRepository::storeLog('notification_reset', "Reset the '{$slug}' notification to its default");

        return [
            'status' => 'success',
            'definition' => NotificationDefinitions::resolve($slug),
        ];
    }
}
