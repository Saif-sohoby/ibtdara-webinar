<?php

namespace App\Http\Controllers;

use App\Models\WebinarParticipantLink;
use App\Models\Participant;
use App\Models\Webinar;
use Illuminate\Http\Request;

class JoinController extends Controller
{
    public function handleJoin($uniqueCode)
    {
        // Find the record by join_code
        $link = WebinarParticipantLink::where('join_code', $uniqueCode)->first();

        if (!$link) {
            abort(404, 'Invalid or expired join link.');
        }

        $webinar = $link->webinar;
        $participant = $link->participant;

        if (!$webinar || !$participant) {
            abort(404, 'Webinar or participant not found.');
        }

        // Append '_attended' to webinar tags
$webinarTags = is_array($webinar->tags) ? $webinar->tags : (json_decode($webinar->tags, true) ?: []);
        $attendedTags = array_map(fn($tag) => $tag . '_attended', $webinarTags);

        // Merge new tags with participant's existing tags
        $participant->tags = array_unique(array_merge($participant->tags ?? [], $attendedTags));
        $participant->save();

        // Redirect to webinar stream link
        return redirect()->to($webinar->stream_link);
    }
}
