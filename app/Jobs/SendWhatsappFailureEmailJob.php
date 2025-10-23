<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsappFailureEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $phone;
    protected $message;
    protected $error;

    /**
     * Create a new job instance.
     */
    public function __construct($phone, $message, $error = null)
    {
        $this->phone = $phone;
        $this->message = $message;
        $this->error = $error;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::raw(
            "WhatsApp message failed.\n\nPhone: {$this->phone}\nMessage: {$this->message}\nError: {$this->error}",
            function ($mail) {
                $mail->to('ahmad@sohoby.sa')
                    ->subject('WhatsApp Message Failed');
            }
        );
    }
}
