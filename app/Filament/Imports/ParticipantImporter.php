<?php

namespace App\Filament\Imports;

use App\Models\Participant;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class ParticipantImporter extends Importer
{
    protected static ?string $model = Participant::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->rules(['max:255']),

            ImportColumn::make('email')
                ->rules(['nullable', 'email', 'max:255']), // Email is optional but must be valid

            ImportColumn::make('mobile')
                ->rules(['required', 'max:255']), // Ensure mobile is required for uniqueness

            ImportColumn::make('tags')
                ->array(',') // Converts comma-separated values into an array
                ->rules(['nullable']), 

            ImportColumn::make('sources')
                ->array(',') // Converts comma-separated values into an array
                ->rules(['nullable']),
        ];
    }

    public function resolveRecord(): ?Participant
    {
        // Try to find an existing record by `mobile`, update it if found
        return Participant::firstOrNew([
            'mobile' => $this->data['mobile'], // Unique identifier
        ]);
    }

    protected function beforeSave(): void
    {
        // Ensure empty arrays are stored correctly
        $this->record->tags = $this->record->tags ?? [];
        $this->record->sources = $this->record->sources ?? [];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your participant import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
