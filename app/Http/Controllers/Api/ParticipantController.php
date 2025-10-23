<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ParticipantController extends Controller
{
    public function updateProfile(Request $request)
    {
        try {
            $data = $request->validate([
                'name'        => ['required', 'string', 'max:255'],
                'job_title'  => ['nullable', 'string', 'max:255'],
                'email'      => ['nullable', 'email', 'max:255'],
            ]);

            $participant = $request->user();
            $participant->name = $data['name'];
            $participant->job_title = $data['job_title'] ?? $participant->job_title;
            $participant->email = $data['email'] ?? $participant->email;
            $participant->save();


            return response()->json([
                'success' => true,
                'code'    => 200,
                'message' => 'Profile updated successfully.',
                'data'    => [
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
}
