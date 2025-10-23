<?php

namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    public static function sendMessage(string $phone, string $message)
    {
        // dd('here');
        $payload = ['number' => $phone];
        $payload['message'] = $message;

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.whatsapp.api_key'),
                'Content-Type' => 'application/json',
            ])
                ->post(config('services.whatsapp.endpoint'), $payload);

            // dd($response->body());

            Log::info('[WhatsApp] Sending message', [
                'payload' => $payload,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::error('[WhatsApp] Error sending message', [
                'payload' => $payload,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

}
