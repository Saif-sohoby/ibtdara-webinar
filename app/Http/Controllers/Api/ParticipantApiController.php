<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Participant;
use App\Models\Webinar;
use App\Models\WebinarParticipantLink;
use App\Jobs\SendWebhookJob;
use Carbon\Carbon;

class ParticipantApiController extends Controller
{
    /**
     * Create or update a participant, attach them to an upcoming webinar,
     * generate a join link, and dispatch a webhook if applicable.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerOrUpdate(Request $request)
    {
        // Validate request data (email is optional)
        $request->validate([
            'name'   => 'required|string|max:255',
            'mobile' => 'required|string|max:100',
            'source' => 'required|string|max:255',
            'email'  => 'nullable|email|max:255',
        ]);

        // Retrieve the new source value from the request.
        $newSource = $request->input('source');

        // Attempt to locate an existing participant by mobile number.
        $participant = Participant::where('mobile', $request->mobile)->first();

        if ($participant) {
            // Update the participant's name if it has changed.
            if ($participant->name !== $request->name) {
                $participant->name = $request->name;
            }
            
            // Update email if provided.
            if ($request->filled('email')) {
                $participant->email = $request->email;
            }

            // Merge the new source with any existing ones (ensuring uniqueness).
            $existingSources = is_array($participant->sources) ? $participant->sources : [];
            if (!in_array($newSource, $existingSources)) {
                $existingSources[] = $newSource;
            }
            $participant->sources = $existingSources;
            $participant->save();
        } else {
            // Create a new participant record if one doesn't exist.
            $participant = Participant::create([
                'name'    => $request->name,
                'mobile'  => $request->mobile,
                'email'   => $request->email,
                'sources' => [$newSource],
            ]);
        }

        // Look for an upcoming webinar (one whose start_time is in the future).
        $upcomingWebinar = Webinar::where('start_time', '>', Carbon::now())
            ->orderBy('start_time', 'asc')
            ->first();

        if ($upcomingWebinar) {
            // Attach the participant to the webinar.
            $upcomingWebinar->participants()->syncWithoutDetaching([$participant->id]);

            // Apply registered tags based on the webinar.
            if (method_exists($participant, 'applyRegisteredTagsForWebinar')) {
                $participant->applyRegisteredTagsForWebinar($upcomingWebinar);
            }

            // Generate a unique join link using the WebinarParticipantLink model.
            $link = WebinarParticipantLink::generateUniqueLink($upcomingWebinar->id, $participant->id);
            if ($link) {
                $payload = [
                    'webinar'     => $upcomingWebinar->toArray(),
                    'participant' => $participant->toArray(),
                    'join_link'   => url("/join/{$link->join_code}"),
                ];

                // Dispatch a job to send the webhook.
                $webhookUrl = env('WEBHOOK_URL');  
                SendWebhookJob::dispatch($webhookUrl, $payload);
            }
        }

        // Return a JSON response indicating the result.
        return response()->json([
            'success'          => true,
            'message'          => 'Participant registered/updated successfully.',
            'participant'      => $participant,
            'webinar_attached' => $upcomingWebinar ? true : false,
        ]);
    }
} 