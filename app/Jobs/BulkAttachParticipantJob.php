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

class BulkAttachParticipantJob implements ShouldQueue
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
            Log::error('Invalid webinar for bulk attachment.', [
                'webinar_id' => $this->webinarId,
            ]);
            return;
        }

        // Attach all participants in bulk
        Log::info('Starting bulk attach', ['webinar_id' => $this->webinarId]);

        $webinar->participants()->syncWithoutDetaching($this->participantIds);

        Log::info('Finished bulk attach', ['webinar_id' => $this->webinarId]);



// Apply tags and generate links for each participant
foreach ($this->participantIds as $participantId) {
    $participant = Participant::find($participantId);
    if ($participant) {
        $participant->applyRegisteredTagsForWebinar($webinar);

        $link = WebinarParticipantLink::generateUniqueLink($webinar->id, $participant->id);
        if (!$link) {
            Log::error('Failed to generate join link in bulk attachment.', [
                'webinar_id'   => $webinar->id,
                'participant_id' => $participant->id,
            ]);
        } else {
            Log::debug('Join link generated via bulk attachment.', ['link' => $link->join_code]);

            // 🚀 Explicitly convert start_time to Asia/Riyadh for the webhook
            $webinarData = $webinar->toArray();
            $webinarData['start_time'] = \Carbon\Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $webinar->getRawOriginal('start_time'),
                'Asia/Riyadh'
            )->toIso8601String();

            // 🚀 Optionally, do the same for end_time if needed
            if ($webinar->end_time) {
                $webinarData['end_time'] = \Carbon\Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $webinar->getRawOriginal('end_time'),
                    'Asia/Riyadh'
                )->toIso8601String();
            }

            // 🚀 Log the final converted start_time before dispatching
            Log::info('Dispatching SendWebhookJob with converted start_time', [
                'webinar_id' => $webinar->id,
                'start_time_converted' => $webinarData['start_time'],
            ]);

            $payload = [
                'webinar'     => $webinarData,
                'participant' => $participant->toArray(),
                'join_link'   => url("/join/{$link->join_code}"),
            ];

            $webhookUrl = env('WEBHOOK_URL');
            SendWebhookJob::dispatch($webhookUrl, $payload);
        }
    }
}


        Log::debug('Bulk participant attachment completed.', [
            'webinar_id' => $this->webinarId,
            'participant_ids' => $this->participantIds,
        ]);
    }
}