<?php

namespace App\Jobs;

use App\Models\Participant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;

class SendFcmNotificationJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public array $extra = []
    ) {}


    /**
     * Execute the job.
     */
    // App/Jobs/SendFcmNotificationJob.php

    public function handle(FirebaseService $fcm): void
    {
        $user = Participant::find($this->userId);
        if (!$user) return;

        // Log::info('Sending FCM notification', ['user_id' => $this->userId, 'title' => $this->title]);

        try {
            $resp = $fcm->sendTo($user, $this->title, $this->body, $this->extra);
            // Log::info('FCM service returned', $resp);
        } catch (\Throwable $e) {
            Log::error('FCM sendTo() threw', [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            throw $e; // so the job is marked failed & visible
        }
    }
}
