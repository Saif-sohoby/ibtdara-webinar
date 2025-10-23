<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class WebinarParticipantLink extends Model
{
    use HasFactory;

    protected $fillable = ['webinar_id', 'participant_id', 'join_code'];

    // Generate unique join code for a participant-webinar pair
    public static function generateUniqueLink($webinarId, $participantId)
    {
        Log::debug("Starting generateUniqueLink", compact('webinarId', 'participantId'));

        // Always create a new link, regardless of existing records
        $uniqueCode = Str::random(32);
        Log::debug("Unique code generated", ['unique_code' => $uniqueCode]);

        $newLink = self::create([
            'webinar_id' => $webinarId,
            'participant_id' => $participantId,
            'join_code' => $uniqueCode,
        ]);

        if ($newLink) {
            Log::debug("New join link created", ['newLink' => $newLink->toArray()]);
        } else {
            Log::error("Failed to create join link", compact('webinarId', 'participantId'));
        }

        return $newLink;
    }

    public function webinar()
    {
        return $this->belongsTo(Webinar::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}
 