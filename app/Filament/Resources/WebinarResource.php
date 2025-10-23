<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WebinarResource\Pages;
use App\Models\Webinar;
use App\Services\ZohoService;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Table;
use Filament\Tables\Actions\CreateAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use Webbingbrasil\FilamentCopyActions\Forms\Actions\CopyAction;
use Webbingbrasil\FilamentCopyActions\Tables\Actions\CopyAction as TableCopyAction;
use Filament\Tables\Actions\ReplicateAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Fieldset;
 
class WebinarResource extends Resource
{
    protected static ?string $model = Webinar::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canCreate(): bool
    {
        return true;
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('topic')
                    ->label('Topic')
                    ->required()
                    ->suffixAction(CopyAction::make()),

                Forms\Components\DateTimePicker::make('start_time')
                    ->label('Start Time')
                    ->required()
                    // ->reactive()
                    ->afterStateUpdated(function (callable $set, callable $get) {


                        // Trigger recalculation of reminder timestamps
                        $set('reminder_1', $get('reminder_1'));
                    }),

                Forms\Components\FileUpload::make('thumbnail')
                    ->label('Webinar Thumbnail')
                    ->image()
                    ->imageEditor() // Optional: lets users crop/resize
                    ->directory('webinar-thumbnails') // Store in storage/app/public/webinar-thumbnails
                    ->visibility('public') // Make publicly accessible if needed
                    ->required(true),


                Forms\Components\TextInput::make('registration_link')
                    ->label('Registration Link')
                    ->disabled()
                    ->suffixAction(CopyAction::make()),

                Forms\Components\TextInput::make('registration_count')
                    ->label('Registration Count')
                    ->disabled()
                    ->afterStateHydrated(function ($component, $state, $record) {
                        $component->state($record ? $record->participants_count : 0);
                    }),

                TagsInput::make('tags')
                    ->label('Tags')
                    ->placeholder('Add tags')
                    ->suggestions([
                        'Business',
                        'Education',
                        'Technology',
                        'Health',
                    ]),

                Forms\Components\TextInput::make('stream_link')
                    ->label('Stream Link')
                    ->required()
                    ->url()
                    ->placeholder('Enter the live stream link')
                    ->suffixAction(CopyAction::make()),

                // ✅ Replaced checkbox list with manual input reminders
                Forms\Components\Fieldset::make('Reminder Timings')
                    ->label('Reminder Schedule (Hours Before Start)')
                    ->schema([
                        Forms\Components\TextInput::make('reminder_1')
                            ->numeric()
                            ->label('First Reminder')
                            ->suffix('hrs')
                            ->default(24)
                            ->reactive()
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record && isset($record->reminder_offsets[0]) && $record->start_time) {
                                    $component->state(
                                        Carbon::parse($record->reminder_offsets[0])->diffInMinutes(Carbon::parse($record->start_time)) / 60
                                    );
                                }
                            }),
                        Forms\Components\TextInput::make('reminder_2')
                            ->numeric()
                            ->label('Second Reminder')
                            ->suffix('hrs')
                            ->default(6)
                            ->reactive()
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record && isset($record->reminder_offsets[1]) && $record->start_time) {
                                    $component->state(
                                        Carbon::parse($record->reminder_offsets[1])->diffInMinutes(Carbon::parse($record->start_time)) / 60
                                    );
                                }
                            }),
                        Forms\Components\TextInput::make('reminder_3')
                            ->numeric()
                            ->label('Third Reminder')
                            ->suffix('hrs')
                            ->default(1)
                            ->reactive()
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record && isset($record->reminder_offsets[2]) && $record->start_time) {
                                    $component->state(
                                        Carbon::parse($record->reminder_offsets[2])->diffInMinutes(Carbon::parse($record->start_time)) / 60
                                    );
                                }
                            }),
                        Forms\Components\TextInput::make('reminder_4')
                            ->numeric()
                            ->label('Fourth Reminder')
                            ->suffix('hrs')
                            ->default(0.25)
                            ->reactive()
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record && isset($record->reminder_offsets[3]) && $record->start_time) {
                                    $component->state(
                                        Carbon::parse($record->reminder_offsets[3])->diffInMinutes(Carbon::parse($record->start_time)) / 60
                                    );
                                }
                            }),
                    ])
                    ->columns(2)
                    ->afterStateUpdated(function (callable $get, callable $set) {
                        $start = $get('start_time');

                        if ($start) {
                            $reminderHours = collect([
                                $get('reminder_1'),
                                $get('reminder_2'),
                                $get('reminder_3'),
                                $get('reminder_4'),
                            ])->filter(); // Remove nulls

                            $timestamps = $reminderHours->map(function ($hours) use ($start) {
                                return Carbon::parse($start)->subMinutes($hours * 60)->toIso8601String();
                            })->values()->toArray();

                            $set('reminder_offsets', $timestamps);
                        }
                    }),

                Forms\Components\Hidden::make('reminder_offsets')
                    ->dehydrated()
                    ->default([]),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->label('Thumbnail')
                    ->disk('public') // Use the correct disk
                    ->toggleable(),

                Tables\Columns\TextColumn::make('topic')->searchable(),
                TagsColumn::make('tags')->label('Tags')->searchable(),
                Tables\Columns\TextColumn::make('start_time')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Registrations')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('start_time', 'desc')   // ← add this line
            ->actions([
                ActionGroup::make([

                    Tables\Actions\EditAction::make(),
                    ReplicateAction::make()
                        ->beforeReplicaSaved(function ($replica) {
                            unset($replica->participants_count); // Remove the virtual column
                        }),


                    TableCopyAction::make()
                        ->copyable(fn($record) => $record->registration_link)
                        ->label('Reg'),

                    TableCopyAction::make()
                        ->copyable(fn($record) => $record->stream_link)
                        ->label('Stream'),
                    DeleteAction::make(),

                ])
            ])
            ->headerActions([])

            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(), // Add this for bulk delete functionality
                Tables\Actions\BulkAction::make('attachParticipants')
                    ->label('Attach Participants')
                    ->icon('heroicon-o-users')
                    ->modalHeading('Attach Existing Participants')
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\Toggle::make('attach_existing')
                            ->label('Limit Participants?')
                            ->reactive()
                            ->afterStateUpdated(fn($state, callable $set) => $set('show_limit_input', $state)),
                        Forms\Components\TextInput::make('participant_limit')
                            ->label('Limit')
                            ->numeric()
                            ->visible(fn(callable $get) => $get('attach_existing')),
                        Forms\Components\TagsInput::make('include_tags')
                            ->label('Include Participants with Tags')
                            ->placeholder('Add tags to include'),
                        Forms\Components\TagsInput::make('exclude_tags')
                            ->label('Exclude Participants with Tags')
                            ->placeholder('Add tags to exclude'),
                    ])
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                        $query = \App\Models\Participant::query();

                        if (!empty($data['include_tags'])) {
                            $query->whereJsonContains('tags', $data['include_tags']);
                        }

                        if (!empty($data['exclude_tags'])) {
                            $query->where(function ($q) use ($data) {
                                foreach ($data['exclude_tags'] as $excludeTag) {
                                    $q->whereJsonDoesntContain('tags', $excludeTag);
                                }
                            });
                        }

                        if (!empty($data['participant_limit'])) {
                            $query->limit((int) $data['participant_limit']);
                        }

                        $participants = $query->get();
                        $zohoService = app(\App\Services\ZohoService::class);

                        foreach ($records as $record) {
                            // Attach participants to the webinar
                            $record->participants()->attach($participants->pluck('id'));

                            // Prepare registrants array for Zoho registration
                            $registrants = [];
                            foreach ($participants as $participant) {
                                // New code: apply merged registered tags for the webinar
                                $participant->applyRegisteredTagsForWebinar($record);

                                $registrants[] = [
                                    'email'     => $participant->email,
                                    'firstName' => $participant->name,
                                    'lastName'  => '', // Optional
                                ];
                            }

                            // Register participants on Zoho
                            if (!empty($registrants)) {
                                try {
                                    $zohoService->registerParticipantsInWebinar(
                                        $record->zoho_webinar_id,
                                        $registrants,
                                        $record->instance_id
                                    );
                                } catch (\Exception $exception) {
                                    Notification::make()
                                        ->title('Error')
                                        ->danger()
                                        ->body('Failed to register participants on Zoho: ' . $exception->getMessage())
                                        ->send();
                                }
                            }
                        }

                        Notification::make()
                            ->title('Success')
                            ->body('Participants attached successfully to the selected webinars!')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

            ]);
    }


    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\WebinarResource\RelationManagers\ParticipantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebinars::route('/'),
            'view'  => Pages\ViewWebinar::route('/{record}'),
            // Optionally, keep the edit page if needed:
            'edit'  => Pages\EditWebinar::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withCount('participants');
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        return static::calculateReminderOffsets($data);
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        return static::calculateReminderOffsets($data);
    }

    protected static function calculateReminderOffsets(array $data): array
    {
        if (isset($data['start_time'])) {
            $reminderHours = collect([
                $data['reminder_1'] ?? null,
                $data['reminder_2'] ?? null,
                $data['reminder_3'] ?? null,
                $data['reminder_4'] ?? null,
            ])->filter();

            $data['reminder_offsets'] = $reminderHours->map(function ($hours) use ($data) {
                return Carbon::parse($data['start_time'])->timezone('Asia/Riyadh')->subMinutes($hours * 60)->toIso8601String();
            })->values()->toArray();
        }

        return $data;
    }
}
