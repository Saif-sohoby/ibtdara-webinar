<?php

namespace App\Jobs;

use App\Models\Webinar;
use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DetachParticipantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $webinarId;
    protected int $participantId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $webinarId, int $participantId)
    {
        $this->webinarId = $webinarId;
        $this->participantId = $participantId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $webinar = Webinar::find($this->webinarId);
        $participant = Participant::find($this->participantId);

        if (!$webinar || !$participant) {
            Log::error('Invalid webinar or participant for detachment.', [
                'webinar_id'   => $this->webinarId,
                'participant_id' => $this->participantId,
            ]);
            return;
        }

        // Detach the participant
        $webinar->participants()->detach($participant->id);

        Log::debug('Participant detached via queue.', [
            'webinar_id' => $this->webinarId,
            'participant_id' => $this->participantId,
        ]);
    }
}
