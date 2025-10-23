<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BroadcastWebinarNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int    $webinarId,
        public string $segment,
        public string $customMessage
    ) {}

    public function via($notifiable): array
    {
        // sirf database channel
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title'          => 'Broadcast Message',
            'type'           => 'webinar_broadcast',
            'webinar_id'     => $this->webinarId,
            'segment'        => $this->segment,
            'message'        => $this->customMessage,
            // Optional helpers for UI:
            'notifiable_id'  => $notifiable->id,
            'created_at'     => now()->toIso8601String(),
        ];
    }
}
