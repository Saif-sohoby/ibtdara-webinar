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

        // Ensure webinar tags are in array format.
        $webinarTags = is_array($webinar->tags)
            ? $webinar->tags
            : (json_decode($webinar->tags, true) ?: []);

        // Retrieve all participants attached to the webinar.
        $participants = $webinar->participants;

        $filteredParticipants = collect();

        // Filter for registrations (excluding attendees)
        if ($this->segment === 'registrations' || $this->segment === 'both') {
            $registrationTags = array_map(
                fn($tag) => $tag . '_registered',
                $webinarTags
            );

            $attendedTags = array_map(
                fn($tag) => $tag . '_attended',
                $webinarTags
            );

            $registrations = $participants->filter(function ($participant) use ($registrationTags, $attendedTags) {
                $pTags = is_array($participant->tags)
                    ? $participant->tags
                    : (json_decode($participant->tags, true) ?: []);

                // Must have all registration tags and NONE of the attended tags
                return collect($registrationTags)->every(fn($tag) => in_array($tag, $pTags)) &&
                       collect($attendedTags)->every(fn($tag) => !in_array($tag, $pTags));
            });

            $filteredParticipants = $filteredParticipants->merge($registrations);
        }

        // Filter for attendees
        if ($this->segment === 'attendees' || $this->segment === 'both') {
            $attendedTags = array_map(
                fn($tag) => $tag . '_attended',
                $webinarTags
            );

            $attendees = $participants->filter(function ($participant) use ($attendedTags) {
                $pTags = is_array($participant->tags)
                    ? $participant->tags
                    : (json_decode($participant->tags, true) ?: []);

                // Must have all attended tags
                return collect($attendedTags)->every(fn($tag) => in_array($tag, $pTags));
            });

            $filteredParticipants = $filteredParticipants->merge($attendees);
        }

        // Remove duplicate participants (if any).
        $filteredParticipants = $filteredParticipants->unique('id');

        // Prepare the payload.
        $payload = [
            'webinar_id'     => $webinar->id,
            'custom_message' => $this->customMessage,
            'participants'   => $filteredParticipants->map(function ($participant) {
                return [
                    'id'    => $participant->id,
                    'name'  => $participant->name,
                    'email' => $participant->email,
                    'mobile'=> $participant->mobile,
                    'tags'  => $participant->tags,
                ];
            })->values(), // Re-index the collection.
        ];

        // Use a temporary webhook URL. Replace with your real endpoint later.
        $webhookUrl = 'https://flow.sohoby.com:2087/webhook/d6fc8916-85b5-486b-9356-f93360b79b4c1';

        $response = Http::post($webhookUrl, $payload);

        if ($response->failed()) {
            Log::error("BroadcastMessageJob: Webhook call failed.", ['response' => $response->body()]);
        } else {
            Log::info("BroadcastMessageJob: Broadcast sent successfully for webinar ({$webinar->id}).");
        }
    }
} 
