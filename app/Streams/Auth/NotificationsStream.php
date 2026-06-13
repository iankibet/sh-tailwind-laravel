<?php

namespace App\Streams\Auth;

use App\Models\User;
use Iankibet\Streamline\Stream;

class NotificationsStream extends Stream
{
    /**
     * The current user's unread notifications (for the dashboard dropdown).
     *
     * @return array{status: string, notifications: array<int, array<string, mixed>>, unread_count: int}
     */
    public function list(): array
    {
        /** @var User $user */
        $user = request()->user();

        return [
            'status' => 'success',
            'notifications' => $this->present($user->unreadNotifications()->latest()->limit(20)->get()),
            'unread_count' => $user->unreadNotifications()->count(),
        ];
    }

    /**
     * The current user's full notification history (for the "all notifications" page).
     *
     * @return array{status: string, notifications: array<int, array<string, mixed>>, unread_count: int}
     */
    public function all(): array
    {
        /** @var User $user */
        $user = request()->user();

        return [
            'status' => 'success',
            'notifications' => $this->present($user->notifications()->latest()->limit(100)->get()),
            'unread_count' => $user->unreadNotifications()->count(),
        ];
    }

    /**
     * Mark a single notification as read and return its action URL (if any) so
     * the dashboard can redirect there.
     *
     * @return array{status: string, action_url: string|null}
     */
    public function read(string $id): array
    {
        /** @var User $user */
        $user = request()->user();

        $notification = $user->notifications()->whereKey($id)->first();
        abort_if($notification === null, 404, 'Notification not found.');

        $notification->markAsRead();

        return [
            'status' => 'success',
            'action_url' => $notification->data['action_url'] ?? null,
        ];
    }

    /** @return array{status: string} */
    public function markAllRead(): array
    {
        /** @var User $user */
        $user = request()->user();
        $user->unreadNotifications->markAsRead();

        return ['status' => 'success'];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Notifications\DatabaseNotification>  $notifications
     * @return array<int, array<string, mixed>>
     */
    private function present($notifications): array
    {
        return $notifications->map(fn ($notification) => [
            'id' => $notification->id,
            'subject' => $notification->data['subject'] ?? '',
            'message' => $notification->data['message'] ?? '',
            'action_url' => $notification->data['action_url'] ?? null,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ])->all();
    }
}
