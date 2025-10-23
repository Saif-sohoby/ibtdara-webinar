<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Webinar;
use App\Models\Participant;
use Carbon\Carbon;

class IbtdaraRegistrationController extends Controller
{
    /**
     * Check for an upcoming webinar with the "ibtdara" tag.
     * If found, redirect to its registration URL.
     * Otherwise, show a custom registration form.
     */
    public function showOrRedirect()
    {
        // Assuming your webinars table has a JSON "tags" column cast to an array,
        // you can use whereJsonContains to filter by tag.
        $upcomingWebinar = Webinar::whereJsonContains('tags', 'ibtdara')
            ->where('start_time', '>', now())
            ->orderBy('start_time', 'asc')
            ->first();

        if ($upcomingWebinar) {
            return redirect()->to($upcomingWebinar->registration_link);
        } else {
            return view('ibtdara.upcoming-registration'); // Create this Blade view
        }
    }

    /**
     * Process the form submission for upcoming webinars when no webinar is found.
     */
    public function registerParticipant(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'mobile' => 'required|string|max:20',
            'email'  => 'nullable|email|max:255',
        ]);

        // Check if a participant with the given mobile already exists
        $participant = Participant::where('mobile', $request->mobile)->first();

        if ($participant) {
            // Update name and email if participant already exists
            $participant->update([
                'name'  => $request->name,
                'email' => $request->email,
            ]);
        } else {
            // Create a new participant
            $participant = Participant::create([
                'name'   => $request->name,
                'mobile' => $request->mobile,
                'email'  => $request->email,
            ]);
        }

        // Append the "ibtdara_registered" tag to the participant without attaching them to any webinar.
        $existingTags = is_array($participant->tags) ? $participant->tags : [];
        $existingTags[] = 'ibtdara_registered';
        $participant->tags = array_unique($existingTags);
        $participant->save();

        return redirect()->back()->with('success', 'Thank you for registering for our upcoming webinar! We will inform you when a new webinar is available.');
    }
}
