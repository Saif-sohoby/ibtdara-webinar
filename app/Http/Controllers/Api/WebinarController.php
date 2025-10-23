<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookJob;
use App\Models\Webinar;
use App\Models\WebinarParticipantLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebinarController extends Controller
{
    public function index(Request $request)
    {
        $participant = $request->user(); // Sanctum se aayega (Participant tokens)

        $query = Webinar::query()
            ->latest()
            ->select('id', 'topic', 'start_time', 'thumbnail');

        if ($participant) {
            // Adds a boolean column `is_joined` per row (0/1)
            $query->withExists([
                'participants as is_joined' => function ($q) use ($participant) {
                    $q->where('participants.id', $participant->id);
                },
            ]);
        }

        $webinars = $query->paginate(10);

        $webinars->getCollection()->transform(function ($webinar) use ($participant) {
            return [
                'id'         => $webinar->id,
                'topic'      => $webinar->topic,
                'start_time' => $webinar->start_time,
                'thumbnail'  => $webinar->thumbnail_url,
                'is_joined'  => $participant ? (bool) ($webinar->is_joined ?? 0) : false,
            ];
        });

        return response()->json([
            'success' => true,
            'code'    => 200,
            'message' => 'Webinars fetched successfully.',
            'data'    => $webinars,
        ]);
    }

    public function show(Request $request, $id)
    {
        $participant = $request->user();

        $query = Webinar::query()->select('id', 'topic', 'start_time', 'thumbnail', 'stream_link');

        // Add joined check for logged-in participant
        if ($participant) {
            $query->withExists([
                'participants as is_joined' => function ($q) use ($participant) {
                    $q->where('participants.id', $participant->id);
                },
            ]);
        }

        $webinar = $query->find($id);

        if (!$webinar) {
            return response()->json([
                'success' => false,
                'code'    => 404,
                'message' => 'Webinar not found.',
            ], 200);
        }

        return response()->json([
            'success' => true,
            'code'    => 200,
            'message' => 'Webinar details fetched successfully.',
            'data'    => [
                'id'          => $webinar->id,
                'topic'       => $webinar->topic,
                'start_time'  => $webinar->start_time,
                'thumbnail'   => $webinar->thumbnail_url,
                'stream_link' => $webinar->stream_link,
                'is_joined'   => $participant ? (bool) ($webinar->is_joined ?? 0) : false,
            ],
        ], 200);
    }

    public function joinWebinar(Request $request, $id)
    {
        $webinar = Webinar::find($id);

        if (!$webinar) {
            return response()->json([
                'success' => false,
                'code'    => 404,
                'message' => 'Webinar not found.',
            ], 200);
        }

        $participant = $request->user();

        $webinar->participants()->syncWithoutDetaching([$participant->id]);
        $participant->applyRegisteredTagsForWebinar($webinar);

        // Generate a unique join link for the participant
        $link = WebinarParticipantLink::generateUniqueLink($webinar->id, $participant->id);

        if ($link) {
            $this->sendWebhook($link, $webinar, $participant);
        }

        return response()->json([
            'success' => true,
            'code'    => 200,
            'message' => 'Webinar joined successfully.',
        ], 200);
    }

    protected function sendWebhook($link, $webinar, $participant)
    {
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
}
