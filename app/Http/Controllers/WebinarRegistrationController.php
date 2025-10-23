<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Webinar;
use App\Models\Participant;
use App\Models\WebinarParticipantLink;
use App\Jobs\SendWebhookJob;


class WebinarRegistrationController extends Controller
{
    public function showForm($token)
    {
        $webinar = Webinar::where('registration_link', url('/register/' . $token))->firstOrFail();

        return view('webinar.registration', compact('webinar'));
    }

    public function registerParticipant(Request $request, $token)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'sources' => 'nullable|string',
        ]);

        $webinar = Webinar::where('registration_link', url('/register/' . $token))->firstOrFail();

        // Convert sources string into an array
        $newSources = $request->sources ? explode(',', $request->sources) : [];

        // Check if a participant with this mobile number exists
        $participant = Participant::where('mobile', $request->mobile)->first();

        if ($participant) {
            $participant->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            $existingSources = is_array($participant->sources) ? $participant->sources : [];
            $mergedSources = array_unique(array_merge($existingSources, $newSources));
            // $participant->update(['sources' => $mergedSources]);
        } else {
            $participant = Participant::create([
                'name' => $request->name,
                'mobile' => $request->mobile,
                'email' => $request->email,
                // 'sources' => $newSources,
            ]);
        }

        // Attach participant to the webinar
        $webinar->participants()->syncWithoutDetaching([$participant->id]);

        // Assign '_registered' tag
        $participant->applyRegisteredTagsForWebinar($webinar);

        // **Generate join link and dispatch webhook with adjusted timezone**
        $link = WebinarParticipantLink::generateUniqueLink($webinar->id, $participant->id);
        if ($link) {
            $webinarData = $webinar->toArray();

            // Log raw DB value and converted value
            \Log::info('Webhook Payload Start Time:', [
                'raw_db_value' => $webinar->getRawOriginal('start_time'),
                'converted_value' => \Carbon\Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $webinar->getRawOriginal('start_time'),
                    'Asia/Riyadh'
                )->toIso8601String(),
            ]);

            $webinarData['start_time'] = \Carbon\Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $webinar->getRawOriginal('start_time'),
                'Asia/Riyadh'
            )->toIso8601String();

            $payload = [
                'webinar'     => $webinarData,
                'participant' => $participant->toArray(),
                'join_link'   => url("/join/{$link->join_code}"),
            ];

            $webhookUrl = env('WEBHOOK_URL');
            SendWebhookJob::dispatch($webhookUrl, $payload);
        }


        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'You have successfully registered!']);
        }

        return redirect()->back()->with('success', 'You have successfully registered!');
    }
}
