<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

/**
 * Stored override for a notification definition. A row exists only when an
 * admin has edited the shipped default (see config/notifications.php and
 * App\Services\NotificationDefinitions).
 */
class NotificationMessage extends Model
{
    protected $fillable = [
        'slug',
        'subject',
        'mail',
        'sms',
        'whatsapp',
        'channels',
        'action_label',
        'action_url',
    ];

    protected $casts = [
        'channels' => 'array',
    ];
}
