<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsappFailureEmailJob;
use App\Models\Participant;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    protected $app_mode;

    public function __construct()
    {
        $this->app_mode = config('app.app_mode');
    }

    public function requestOtp(Request $request)
    {
        try {
            $data = $request->validate([
                'phone'       => ['required', 'string', 'max:30'],
                'fcm_token'  => ['nullable', 'string', 'max:255'],
            ]);

            // dd($data);
            $phone = $this->normalizePhone($data['phone']);

            // Optional: Agar sirf existing users ko OTP bhejna hai:
            $is_new_participant = false;
            $participant = Participant::where('mobile', $phone)->first();
            if (!$participant) {
                $participant = Participant::create([
                    'mobile' => $phone,
                ]);
                $is_new_participant = true;
            }

            if ($participant->name === null) {
                $is_new_participant = true;
            }

            if ($this->app_mode === 'production') {
                $throttleKey = "otp-requests:{$phone}";
                if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                    $retry = RateLimiter::availableIn($throttleKey);
                    return response()->json([
                        'success' => false,
                        'code'    => 429,
                        'message' => 'Too many OTP requests. Please try again later.',
                        'retry_after_seconds' => $retry,
                    ], 200);
                }
                RateLimiter::hit($throttleKey, 60 * 60); // decay in 1 hour
            }

            // Generate 4-digit OTP
            $otp = (string) rand(1000, 9999);

            $participant->otp = $otp;
            $participant->otp_expires_at = now()->addMinutes(5);
            if (isset($data['fcm_token'])) {
                $participant->fcm_token = $data['fcm_token'];
            }
            $participant->save();

            if ($this->app_mode == 'production') {
                $response = $this->SendOtp($phone, $otp);
                if (!$response) {
                    return response()->json([
                        'success' => false,
                        'code'    => 500,
                        'message' => 'Failed to send OTP. Please try again later.',
                    ], 200);
                }
            }

            // logger()->info("OTP for {$phone}: {$otp}");

            return response()->json([
                'success' => true,
                'code'    => 200,
                'is_new_participant' => $is_new_participant,
                'message' => 'OTP has been sent.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'code'    => 422,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'code'    => 500,
                'message' => 'Something went wrong.',
                'error'   => app()->environment('production') ? null : $e->getMessage(),
            ], 200);
        }
    }

    protected function SendOtp($phone, $message)
    {
        try {
            $response = WhatsappService::sendMessage(
                $phone,
                $message
            );

            $raw = $response->body();
            $responseBody = json_decode($raw, true);

            $isSuccess = is_array($responseBody)
                && ($responseBody['status'] ?? false) === true
                && ($responseBody['message'] ?? '') === 'Message sent.';

            $status = $isSuccess ? 'success' : 'failed';

            // throw new \Exception('Forced failure for testing email job');

            return $isSuccess;
        } catch (\Throwable $e) {
            $logMessage = $e->getMessage();
            Log::error('[SendWhatsAppMessage] Exception caught', [
                'phone' => $phone,
                'error' => $logMessage,
            ]);

            dispatch(new SendWhatsappFailureEmailJob(
                $phone,
                $message,
                $logMessage
            ));

            return false;
        }
    }

    /**
     * POST /auth/login/verify-otp
     * Body: { phone: string, otp: string, device_name?: string }
     */
    public function verifyOtp(Request $request)
    {
        try {
            $data = $request->validate([
                'phone'       => ['required', 'string', 'max:30'],
                'otp'         => ['required', 'digits:4'],
            ]);

            $phone    = $this->normalizePhone($data['phone']);
            $otpInput = $data['otp'];
            $device   = 'api';

            // dd($data);

            $participant = Participant::where('mobile', $phone)->first();
            if (!$participant) {
                // Extremely unlikely if you gated requestOtp, but handle anyway
                return response()->json([
                    'success' => false,
                    'code'    => 404,
                    'message' => 'participant not found.',
                ], 200);
            }

            $is_new_participant = false;
            if ($participant->name === null) {
                $is_new_participant = true;
            }

            if ($this->app_mode !== 'production') {
                // Non-prod backdoor OTP
                if ($otpInput !== '8888') {
                    return response()->json([
                        'success' => false,
                        'code'    => 401,
                        'message' => 'Invalid OTP.',
                    ], 200);
                }
            } else {
                $storedOtp = (string) ($participant->otp ?? '');
                $isValid   = hash_equals($storedOtp, (string) $otpInput);

                $isExpired = $participant->otp_expires_at
                    ? now()->greaterThan($participant->otp_expires_at)
                    : false;

                if (! $isValid || $isExpired) {
                    return response()->json([
                        'success' => false,
                        'code'    => 401,
                        'message' => 'Invalid OTP.',
                    ], 200);
                }
            }

            $participant->otp = null;
            $participant->otp_expires_at = null;
            $participant->save();

            $token = $participant->createToken($device)->plainTextToken;

            return response()->json([
                'success' => true,
                'code'    => 200,
                'token'   => $token,
                'is_new_participant' => $is_new_participant,
                'participant'    => [
                    'id'    => $participant->id,
                    'name'  => $participant->name,
                    'email' => $participant->email,
                    'phone' => $participant->mobile,
                    'job_title' => $participant->job_title,
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'code'    => 422,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'code'    => 500,
                'message' => 'Something went wrong.',
                'error'   => app()->environment('production') ? null : $e->getMessage(),
            ], 200);
        }
    }

    /**
     * Phone normalization (minimal). Customize as needed (E.164 etc.)
     */
    protected function normalizePhone(string $raw): string
    {
        // Example: remove spaces/dashes, ensure leading +
        $p = preg_replace('/\s+|-/', '', $raw);
        if ($p && $p[0] !== '+') {
            // If your participant base is KSA, you could auto-prepend +966 when starting with 0/5.
            // For now, return as-is without country assumption.
            return $p;
        }
        return $p;
    }
}
