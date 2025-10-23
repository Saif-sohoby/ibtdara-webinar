<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $webhookUrl;
    protected array $payload;

    /**
     * Create a new job instance.
     */
    public function __construct(string $webhookUrl, array $payload)
    {
        $this->webhookUrl = $webhookUrl;
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $response = Http::timeout(50) 
                ->retry(1000, 5000) 
                ->post($this->webhookUrl, $this->payload);

            // Log the response status and body
            Log::info('Webhook sent successfully', [
                'url' => $this->webhookUrl,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('Webhook failed', [
                'url' => $this->webhookUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
