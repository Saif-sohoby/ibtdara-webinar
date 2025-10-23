<?php

namespace App\Observers;

use App\Jobs\SendFcmNotificationJob;
// use App\Models\Notification;
use Illuminate\Notifications\DatabaseNotification as Notification;
use App\Models\Participant;
use Illuminate\Support\Facades\Log;

class NotificationObserver
{
    public function created(Notification $notification): void
    {
        // Log::info('Notification created', ['id' => $notification->id]);
        // dd($notification);
        if ($notification->notifiable_type !== Participant::class) {
            return; // only users for now
        }

        $userId = (int) $notification->notifiable_id;

        // Pull title/body from the stored payload, with sane fallbacks
        $payload = (array) ($notification->data ?? []);
        $title = $payload['title'] ?? ($payload['subject'] ?? 'New Notification');
        $body  = $payload['body']  ?? ($payload['message'] ?? 'You have a new message');

        // You can pass the rest of payload as extra data:
        // $extra = $payload['extra'] ?? ($notification->extra_data ?? []);
        $extra = ['notification_id' => $notification->id, 'data' => $payload];

        SendFcmNotificationJob::dispatch($userId, $title, $body, is_array($extra) ? $extra : []);
    }
}
