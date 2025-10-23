<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParticipantResource\Pages;
use App\Models\Participant;
use App\Models\Webinar;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\WebinarParticipantLink; // Import the new model
use Filament\Tables\Actions\ReplicateAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Support\Str;






class ParticipantResource extends Resource
{
    protected static ?string $model = Participant::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('mobile')
                    ->label('Mobile')
                    ->maxLength(255),
                TagsInput::make('tags')
    ->label('Tags')
    ->placeholder('Add tags')
    ->suggestions([
        'ibtdara_registered',
        'ibtdara_attended',
        'sohoby_registered',
        'sohoby_attended',
    ])
    ->default(fn ($record) => is_array($record?->tags) ? $record->tags : json_decode($record?->tags, true) ?? []),
            
            TagsInput::make('sources')
                ->label('Sources')
                ->placeholder('Add sources')
                ->suggestions([
                    'Instagram',
                    'Twitter',
                    'Facebook',
                    'LinkedIn',
                    'Google Ads',
                    'Referral',
                ])
                ->columnSpanFull(), // Makes it wider
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->deferLoading() // ? lazy load added
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
            ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('mobile')
                    ->label('Mobile')
                    ->searchable(),
                Tables\Columns\TagsColumn::make('tags')
                    ->label('Tags'),
            Tables\Columns\TagsColumn::make('sources')
                ->label('Sources')
                ->separator(', ')
                ->toggleable(), // Allows hiding this column if needed
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
        	->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('created_at_range')
        ->label('Created Between')
        ->form([
            DateTimePicker::make('from')
                ->label('From')
                ->seconds(false), // set true if you store seconds
            DateTimePicker::make('until')
                ->label('Until')
                ->seconds(false),
        ])
        ->query(function (Builder $query, array $data): Builder {
            return $query
                ->when(
                    $data['from'] ?? null,
                    fn (Builder $q, $date) => $q->where('created_at', '>=', $date)
                )
                ->when(
                    $data['until'] ?? null,
                    fn (Builder $q, $date) => $q->where('created_at', '<=', $date)
                );
        })
        // Show a nice pill with the chosen range
        ->indicateUsing(function (array $data): ?string {
            $from = $data['from'] ?? null;
            $until = $data['until'] ?? null;

            if ($from && $until) {
                return "Created: {$from} ? {$until}";
            }
            if ($from) {
                return "Created: from {$from}";
            }
            if ($until) {
                return "Created: until {$until}";
            }

            return null;
        }),
            Filter::make('wrong_numbers')
    ->label('Wrong Numbers')
    ->query(function ($query) {
        $query->whereRaw("
            NOT (
                mobile LIKE '+%' OR
                mobile LIKE '9%' OR
                mobile LIKE '1%' OR
                mobile LIKE '4%' OR
                mobile LIKE '00%'
            )
        ");
    }),
              Filter::make('tags')
    ->label('Tags')
    ->form([
        TagsInput::make('included_tags')
            ->label('Included Tags')
            ->placeholder('Filter by included tags'),
        TagsInput::make('excluded_tags')
            ->label('Excluded Tags')
            ->placeholder('Filter by excluded tags'),
    ])
    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
        // Included tags logic (OR)
        if (! empty($data['included_tags'])) {
            $query->where(function ($q) use ($data) {
                foreach ($data['included_tags'] as $tag) {
                    $q->orWhere('tags', 'like', '%"' . $tag . '"%');
                }
            });
        }

        // Excluded tags logic (AND)
        if (! empty($data['excluded_tags'])) {
            foreach ($data['excluded_tags'] as $tag) {
                $query->where('tags', 'not like', '%"' . $tag . '"%');
            }
        }

        return $query;
    }),

            Filter::make('sources')
        ->label('Sources')
        ->form([
            TagsInput::make('sources')
                ->label('Sources')
                ->placeholder('Filter by sources')
                ->suggestions([
                    'Instagram',
                    'Twitter',
                    'Facebook',
                    'LinkedIn',
                    'Google Ads',
                    'Referral',
                ]),
        ])
        ->query(fn ($query, $data) => $data['sources'] ? $query->whereJsonContains('sources', $data['sources']) : $query),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(\App\Filament\Imports\ParticipantImporter::class),
            ])
            ->actions([
                ActionGroup::make([

            Tables\Actions\EditAction::make(),
            ReplicateAction::make(),
            DeleteAction::make(),

        

        ])
                
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                BulkAction::make('appendCountryCode')
    ->label('Append Country Code')
    ->icon('heroicon-o-phone') // optional icon
    ->form([
        Forms\Components\TextInput::make('country_code')
            ->label('Country Code')
            ->placeholder('+966')
            ->required(),
    ])
    ->action(function (array $data, \Illuminate\Support\Collection $records) {
        $countryCode = $data['country_code'];

        foreach ($records as $participant) {
            // Prepend the country code to the existing mobile field
            $participant->mobile = $countryCode . $participant->mobile;
            $participant->save();
        }

        Notification::make()
            ->title('Success')
            ->body('Country code appended to selected participants.')
            ->success()
            ->send();
    })
    ->requiresConfirmation(), // ask before executing


                    Tables\Actions\DeleteBulkAction::make(),

                    // bulk attachment starts

                    BulkAction::make('attachToWebinar')
    ->label('Attach to Webinar')
    ->icon('heroicon-o-presentation-chart-bar') 
    ->form([
    Select::make('webinar_id')
        ->label('Select Webinar')
        ->options(Webinar::query()
            ->where('start_time', '>', now()) // Filter future webinars
            ->get()
            ->mapWithKeys(function ($webinar) {
                return [
                    $webinar->id => "{$webinar->topic} ({$webinar->start_time})"
                ];
            }))
        ->searchable()
        ->required(),
]) 
    ->action(function (array $data, $records): void {
        // Find the selected webinar.
        $webinar = Webinar::find($data['webinar_id']);
        if (!$webinar) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body('The selected webinar does not exist.')
                ->send();
            return;
        }

        // Extract participant IDs from the collection
        $participantIds = $records->pluck('id')->toArray();

        // Dispatch a single job for bulk attachment
        $chunks = array_chunk($participantIds, 100); // Split into chunks of 100

        foreach ($chunks as $index => $chunk) {
            \App\Jobs\BulkAttachParticipantJob::dispatch($webinar->id, $chunk);

            // Log the job dispatch
            Log::info('BulkAttachParticipantJob dispatched', [
                'webinar_id' => $webinar->id,
                'webinar_topic' => $webinar->topic,
                'chunk_index' => $index,
                'participant_ids' => $chunk,
                'dispatched_at' => now(),
            ]);
        }
 
        // Notify the user that the bulk attachment has been queued.
        Notification::make()
            ->title('Queued')
            ->success()
            ->body('Bulk attachment has been queued for processing.')
            ->send();
    }),

// bulk attachment ends
                



                // 🚀 Direct Export Action (No Background Processing)
                BulkAction::make('exportParticipants')
                    ->label('Export Selected Participants')
                    ->icon('heroicon-s-cloud-arrow-down') 
                    ->action(function ($records) {
                        // Define file name with timestamp
                        $fileName = 'participants_export_' . now()->format('Y-m-d_H-i-s') . '.csv';

                        // Define CSV headers
                        $headers = [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                        ];

                        // Stream CSV file for direct download
                        return response()->streamDownload(function () use ($records) {
                            $file = fopen('php://output', 'w');

                            // Define CSV column headers
                            fputcsv($file, ['ID', 'Name', 'Email', 'Mobile', 'Job Title', 'Tags', 'Joined At']);

                            // Write each participant's data
                            foreach ($records as $record) {
                                fputcsv($file, [
                                    $record->id,
                                    $record->name,
                                    $record->email,
                                    $record->mobile,
                                    $record->job_title,
                                    implode(',', $record->tags ?? []), // Convert tags array to string
                                    $record->created_at->format('Y-m-d H:i:s'),
                                ]);
                            }

                            fclose($file);
                        }, $fileName, $headers);
                    })
                    ->requiresConfirmation(), // Asks for confirmation before exporting
            ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParticipants::route('/'),
        ];
    }
}
