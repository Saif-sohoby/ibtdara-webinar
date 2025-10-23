<?php

namespace App\Filament\Resources\WebinarResource\Pages;

use App\Filament\Resources\WebinarResource;
use App\Models\Webinar;
use App\Services\ZohoService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Carbon\Carbon;

class EditWebinar extends EditRecord
{
    protected static string $resource = WebinarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Automatically calculate and store reminder_offsets as timestamps before saving.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['start_time'])) {
            $reminderHours = collect([
                $data['reminder_1'] ?? null,
                $data['reminder_2'] ?? null,
                $data['reminder_3'] ?? null,
                $data['reminder_4'] ?? null,
            ])->filter();

            $data['reminder_offsets'] = $reminderHours->map(function ($hours) use ($data) {
                return Carbon::parse($data['start_time'])->subMinutes($hours * 60)->toIso8601String();
            })->values()->toArray();
        }

        return $data;
    }

    /**
     * Fetch attendance from Zoho and tag participants with "attendee".
     */
    protected function fetchAttendanceAndTagParticipants(Webinar $webinar): void
    {
        $zohoService = app(ZohoService::class); // Get the ZohoService instance
        $pageIndex = 0;
        $pageCount = 100; // Fetch 100 attendees per page

        do {
            // Fetch attendee data from Zoho
            $attendees = $zohoService->getWebinarAttendees($webinar->zoho_webinar_id, $pageIndex, $pageCount);

            // Extract emails of attendees
            $emails = collect($attendees)->pluck('email');

            if ($emails->isEmpty()) {
                break; // Stop fetching if no attendees are found
            }

            // Find matching participants in the system
            $participants = $webinar->participants()->whereIn('email', $emails)->get();

            foreach ($participants as $participant) {
                // Assign the "attendee" tag to matching participants
                $participant->tags = array_unique(array_merge($participant->tags ?? [], ['attendee']));
                $participant->save();
            }

            $pageIndex++; // Move to the next page
        } while (count($attendees) === $pageCount); // Continue if the page is full
    }
}
