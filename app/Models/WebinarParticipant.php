<?php

namespace App\Models;

use App\Jobs\SendWebhookJob;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Log;

class WebinarParticipant extends Pivot
{
    /**
     * Boot the model and attach an event listener for when a pivot record is created.
     */
    protected static function booted()
    {
        static::created(function (WebinarParticipant $webinarParticipant) {
            // Ensure related models are loaded
            $webinar = $webinarParticipant->webinar;
            $participant = $webinarParticipant->participant;

            if (!$webinar || !$participant) {
                Log::error('Webinar or Participant not found for webinar participant record.');
                return;
            }

            // Retrieve the unique join link for the participant
            $joinLink = WebinarParticipantLink::where('webinar_id', $webinar->id)
                ->where('participant_id', $participant->id)
                ->first();

            if (!$joinLink) {
                Log::error('No join link found for webinar participant', [
                    'webinar_id' => $webinar->id,
                    'participant_id' => $participant->id,
                ]);
                return;
            }

             

        });
    }

    /**
     * Define the relationship back to the Webinar model.
     */
    public function webinar()
    {
        return $this->belongsTo(Webinar::class);
    }

    /**
     * Define the relationship back to the Participant model.
     */
    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}
 