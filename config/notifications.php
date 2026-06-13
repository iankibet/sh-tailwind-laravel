<?php

/*
|--------------------------------------------------------------------------
| Notification Definitions
|--------------------------------------------------------------------------
|
| Default notification definitions, keyed by slug. These are the shipped
| defaults. Admins may override any of them from the dashboard; an override
| is stored as a row in the `notification_messages` table and takes
| precedence over the matching definition here (see App\Services\
| NotificationDefinitions). Deleting the override ("reset") falls back to
| the default defined below.
|
| Each definition supports {placeholder} tokens in every text field. When
| sending, pass a key => value array to App\Services\Notifier::send() and
| every {key} occurrence is replaced.
|
| Channels:
|   - database : in-app notification shown in the dashboard dropdown
|   - mail     : email
|   - sms      : text message (channel is wired but currently a no-op)
|   - whatsapp : WhatsApp message (channel is wired but currently a no-op)
|
*/

return [

    'welcome' => [
        'subject' => 'Welcome to Pius Videos, {name}',
        'mail' => "Hi {name},\n\nYour account is ready. We're glad to have you on board.\n\nIf you have any questions, just reply to this email.",
        'sms' => 'Hi {name}, welcome to Pius Videos.',
        'whatsapp' => 'Hi {name}, welcome to Pius Videos.',
        'channels' => ['database', 'mail'],
        'action_label' => 'Go to dashboard',
        'action_url' => '/profile',
        'placeholders' => ['name'],
    ],

    'password_reset' => [
        'subject' => 'Your password was changed',
        'mail' => "Hi {name},\n\nYour account password was just changed. If this wasn't you, please contact an administrator immediately.",
        'sms' => 'Hi {name}, your Pius Videos password was just changed.',
        'whatsapp' => 'Hi {name}, your Pius Videos password was just changed.',
        'channels' => ['database', 'mail'],
        'action_label' => 'Review your account',
        'action_url' => '/profile',
        'placeholders' => ['name'],
    ],

    'admin_added' => [
        'subject' => 'You have been added as an administrator',
        'mail' => "Hi {name},\n\nYou have been granted administrator access to Pius Videos for the {department} department by {added_by}.",
        'sms' => 'Hi {name}, you are now an administrator on Pius Videos.',
        'whatsapp' => 'Hi {name}, you are now an administrator on Pius Videos.',
        'channels' => ['database', 'mail'],
        'action_label' => 'Open dashboard',
        'action_url' => '/admins',
        'placeholders' => ['name', 'department', 'added_by'],
    ],

];
