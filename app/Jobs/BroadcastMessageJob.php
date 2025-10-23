<?php

namespace App\Jobs;

use App\Models\Webinar;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\BroadcastWebinarNotification;

class BroadcastMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $webinarId;
    protected $segment;
    protected $customMessage;

    /**
     * Create a new job instance.
     *
     * @param  int    $webinarId
     * @param  string $segment       'registrations', 'attendees', or 'both'
     * @param  string $customMessage
     */
    public function __construct($webinarId, $segment, $customMessage)
    {
        $this->webinarId   = $webinarId;
        $this->segment     = $segment;
        $this->customMessage = $customMessage;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $webinar = Webinar::find($this->webinarId);
        if (!$webinar) {
            Log::error("BroadcastMessageJob: Webinar not found ({$this->webinarId}).");
            return;
        }

        // Ensure webinar tags array format
        $webinarTags = is_array($webinar->tags)
            ? $webinar->tags
            : (json_decode($webinar->tags, true) ?: []);

        // Attached participants
        $participants = $webinar->participants;

        $filteredParticipants = collect();

        if ($this->segment === 'registrations' || $this->segment === 'both') {
            $registrationTags = array_map(fn($t) => $t . '_registered', $webinarTags);
            $attendedTags     = array_map(fn($t) => $t . '_attended',    $webinarTags);

            $registrations = $participants->filter(function ($p) use ($registrationTags, $attendedTags) {
                $pTags = is_array($p->tags) ? $p->tags : (json_decode($p->tags, true) ?: []);
                return collect($registrationTags)->every(fn($tag) => in_array($tag, $pTags)) &&
                    collect($attendedTags)->every(fn($tag) => !in_array($tag, $pTags));
            });

            $filteredParticipants = $filteredParticipants->merge($registrations);
        }

        if ($this->segment === 'attendees' || $this->segment === 'both') {
            $attendedTags = array_map(fn($t) => $t . '_attended', $webinarTags);

            $attendees = $participants->filter(function ($p) use ($attendedTags) {
                $pTags = is_array($p->tags) ? $p->tags : (json_decode($p->tags, true) ?: []);
                return collect($attendedTags)->every(fn($tag) => in_array($tag, $pTags));
            });

            $filteredParticipants = $filteredParticipants->merge($attendees);
        }

        $filteredParticipants = $filteredParticipants->unique('id')->values();

        if ($filteredParticipants->isEmpty()) {
            Log::info("BroadcastMessageJob: No participants to notify for webinar ({$webinar->id}).");
            return;
        }

        $notification = new BroadcastWebinarNotification(
            webinarId: $webinar->id,
            segment: $this->segment,
            customMessage: $this->customMessage
        );

        $filteredParticipants->chunk(500)->each(function ($chunk) use ($notification) {
            Notification::send($chunk, $notification);
        });

        Log::info("BroadcastMessageJob: Database notifications queued for webinar ({$webinar->id}). count={$filteredParticipants->count()}");
    }
}
