<?php

namespace App\Filament\Resources\WebinarResource\RelationManagers;
#namespace App\Filament\Resources\WebinarParticipantRelationManagerResource\RelationManagers;

use App\Models\Participant;
use App\Models\Webinar;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\AttachAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Actions\ExportBulkAction;
use App\Filament\Exports\ParticipantExporter; // Import the exporter class
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Filament\Tables\Actions\BulkAction;
use App\Models\WebinarParticipantLink; // Import the new model
use Webbingbrasil\FilamentCopyActions\Forms\Actions\CopyAction;
use Webbingbrasil\FilamentCopyActions\Tables\Actions\CopyAction as TableCopyAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection; // Make sure to import this
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';
    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Table schema for displaying participants.
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mobile')
                    ->label('Mobile')
                    ->searchable(),
                TagsColumn::make('tags')
                    ->label('Tags')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined At')
                    ->sortable()
                    ->dateTime(),

            ])
            ->headerActions([

                //broadcast
                Action::make('broadcastMessage')
                    ->label('Broadcast Message')
                    ->icon('heroicon-s-megaphone')
                    ->modalHeading('Broadcast Message')
                    ->modalSubheading('Select broadcast segment and write a custom message.')
                    ->form([
                        Select::make('segment')
                            ->label('Select broadcast segment')
                            ->options([
                                'registrations' => 'All Registrations',
                                'attendees'     => 'All Attendees',
                                'both'          => 'Both',
                            ])
                            ->required(),
                        Textarea::make('custom_message')
                            ->label('Write custom message')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $webinar = $this->getOwnerRecord();
                        if (!$webinar) {
                            Notification::make()
                                ->title('Error')
                                ->danger()
                                ->body('Webinar record not found.')
                                ->send();
                            return;
                        }

                        // Dispatch the broadcast job with webinar id, selected segment, and custom message.
                        \App\Jobs\BroadcastMessageJob::dispatch(
                            $webinar->id,
                            $data['segment'],
                            $data['custom_message']
                        );

                        Notification::make()
                            ->title('Queued')
                            ->success()
                            ->body('Broadcast message has been queued for processing.')
                            ->send();
                    })
                    ->requiresConfirmation(), // This adds a confirmation prompt before executing the action.

                // end of broadcast.

                AttachAction::make()
                    ->label('Attach Participant')
                    ->preloadRecordSelect() // Preload participant options
                    ->recordSelectOptionsQuery(function (Builder $query) {
                        return $query->select([
                            'participants.id',
                            'participants.name',
                            'participants.email',
                            'participants.mobile',
                        ]);
                    })
                    ->recordSelectSearchColumns(['participants.name', 'participants.email', 'participants.mobile'])
                    ->before(function (AttachAction $action, RelationManager $livewire, array $data): void {
                        // Ensure the participant ID is provided.
                        if (!isset($data['recordId'])) {
                            Notification::make()
                                ->title('Error')
                                ->danger()
                                ->body('The participant ID was not provided.')
                                ->send();
                            $action->halt(); // Stop further processing.
                            return;
                        }

                        // Get the owner record (the Webinar).
                        $webinar = $livewire->getOwnerRecord();
                        if (!$webinar) {
                            Notification::make()
                                ->title('Error')
                                ->danger()
                                ->body('The webinar record is invalid.')
                                ->send();
                            $action->halt();
                            return;
                        }

                        // Fetch the participant.
                        $participant = Participant::find($data['recordId']);
                        if (!$participant) {
                            Notification::make()
                                ->title('Error')
                                ->danger()
                                ->body('The participant record is not available.')
                                ->send();
                            $action->halt();
                            return;
                        }

                        // Instead of processing synchronously, dispatch the job.
                        \App\Jobs\AttachParticipantJob::dispatch($webinar->id, $participant->id);

                        // Notify the user that the attachment has been queued.
                        Notification::make()
                            ->title('Queued')
                            ->success()
                            ->body('Participant attachment has been queued for processing.')
                            ->send();

                        // Halt the default processing.
                        $action->halt();
                    })

                    ->successNotificationTitle('Participant attached and tagged.')
                    ->failureNotificationTitle('Failed to attach participant.'),
            ])
            ->actions([
                ActionGroup::make([
                    TableCopyAction::make()
                        ->copyable(function ($record) {
                            $webinarId = $this->getOwnerRecord()->id; // Get the webinar ID
                            $participantId = $record->id;             // Get the participant ID

                            // Fetch the join link using both webinar_id and participant_id
                            $link = WebinarParticipantLink::where('webinar_id', $webinarId)
                                ->where('participant_id', $participantId)
                                ->first();

                            return $link ? url("/join/{$link->join_code}") : 'Not available';
                        })
                        ->label('Copy Link')
                        ->tooltip('Copy Join Link'),

                    Tables\Actions\DetachAction::make() // Detach participant
                        ->after(function ($record) {
                            $webinarId = $this->getOwnerRecord()->id;
                            $participantId = $record->id;

                            WebinarParticipantLink::where('webinar_id', $webinarId)
                                ->where('participant_id', $participantId)
                                ->delete();
                        }),
                ])
            ])

            ->bulkActions([
                BulkAction::make('bulkDetach')
                    ->label('Bulk Detach Participants')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $webinar = $this->getOwnerRecord();
                        if (!$webinar) {
                            Notification::make()
                                ->title('Error')
                                ->danger()
                                ->body('The webinar record is invalid.')
                                ->send();
                            return;
                        }

                        $participantIds = $records->pluck('id')->toArray();
                        $chunks = array_chunk($participantIds, 10); // Split into batches of 10

                        foreach ($chunks as $chunk) {
                            \App\Jobs\BulkDetachParticipantJob::dispatch($webinar->id, $chunk);
                        }

                        Notification::make()
                            ->title('Queued')
                            ->success()
                            ->body('Bulk detachment has been queued in batches for faster processing.')
                            ->send();
                    }),

                BulkAction::make('export')
                    ->label('Export Selected Participants')
                    ->action(function ($records) {
                        $webinar = $this->getOwnerRecord();

                        $fileName = 'participants_export_' . now()->format('Y-m-d_H-i-s') . '.csv';
                        $headers = [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                        ];

                        return response()->streamDownload(function () use ($records, $webinar) {
                            $file = fopen('php://output', 'w');

                            // Write CSV headers
                            fputcsv($file, ['ID', 'Name', 'Email', 'Mobile', 'Tags', 'Joined At', 'Join Link']);

                            foreach ($records as $participant) {
                                // Fetch join_code using the pivot table
                                $link = \App\Models\WebinarParticipantLink::where('webinar_id', $webinar->id)
                                    ->where('participant_id', $participant->id)
                                    ->first();

                                $joinLink = $link ? url("/join/{$link->join_code}") : 'N/A';

                                fputcsv($file, [
                                    $participant->id,
                                    $participant->name,
                                    $participant->email,
                                    $participant->mobile,
                                    implode(',', $participant->tags ?? []),
                                    optional($participant->created_at)->format('Y-m-d H:i:s'),
                                    $joinLink,
                                ]);
                            }

                            fclose($file);
                        }, $fileName, $headers);
                    })
                    ->requiresConfirmation()
                    ->icon('heroicon-s-cloud-arrow-down'),

            ]);
    }
}
