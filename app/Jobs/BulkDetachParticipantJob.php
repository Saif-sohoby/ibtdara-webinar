<?php

namespace App\Jobs;

use App\Models\Webinar;
use App\Models\WebinarParticipantLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkDetachParticipantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $webinarId;
    protected array $participantIds;

    /**
     * Create a new job instance.
     */
    public function __construct(int $webinarId, array $participantIds)
    {
        $this->webinarId = $webinarId;
        $this->participantIds = $participantIds;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $webinar = Webinar::find($this->webinarId);

        if (!$webinar) {
            Log::error('Invalid webinar for bulk detachment.', [
                'webinar_id' => $this->webinarId,
            ]);
            return;
        }

        // Delete corresponding unique links from WebinarParticipantLink
        WebinarParticipantLink::where('webinar_id', $this->webinarId)
            ->whereIn('participant_id', $this->participantIds)
            ->delete();

        // Detach all participants in bulk
        $webinar->participants()->detach($this->participantIds);

        Log::debug('Bulk participant detachment completed, and corresponding links removed.', [
            'webinar_id' => $this->webinarId,
            'participant_ids' => $this->participantIds,
        ]);
    }
}