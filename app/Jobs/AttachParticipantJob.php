<?php

namespace App\Jobs;

use App\Models\Webinar;
use App\Models\Participant;
use App\Models\WebinarParticipantLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AttachParticipantJob implements ShouldQueue
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
            Log::error('Invalid webinar or participant for attachment.', [
                'webinar_id'   => $this->webinarId,
                'participant_id' => $this->participantId,
            ]);
            return;
        }

        // Attach the participant (without detaching existing ones)
        $webinar->participants()->syncWithoutDetaching($participant->id);

        // Apply registered tags for the webinar
        $participant->applyRegisteredTagsForWebinar($webinar);

        // Generate a unique join link and store it in the pivot table
        $link = WebinarParticipantLink::generateUniqueLink($webinar->id, $participant->id);
        if (!$link) {
            Log::error('Failed to generate join link in queued attachment.', [
                'webinar_id'   => $webinar->id,
                'participant_id' => $participant->id,
            ]);
        } else {
            Log::debug('Join link generated via queue.', ['link' => $link->join_code]);
        }
    }
}
