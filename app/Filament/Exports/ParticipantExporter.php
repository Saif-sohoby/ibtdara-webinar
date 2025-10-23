<?php

namespace App\Filament\Exports;

use App\Models\Participant;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ParticipantExporter extends Exporter
{
    protected static ?string $model = Participant::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name')->label('Name'),
            ExportColumn::make('email')->label('Email'),
            ExportColumn::make('mobile')->label('Mobile'),
            ExportColumn::make('tags')
                ->label('Tags')
                ->formatStateUsing(fn($state) => is_array($state) ? implode(', ', $state) : $state), // Convert array to string
            ExportColumn::make('sources')
                ->label('Sources')
                ->formatStateUsing(fn($state) => is_array($state) ? implode(', ', $state) : $state), // Convert array to string
            ExportColumn::make('created_at')
                ->label('Joined At')
                ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('Y-m-d H:i:s')),
            ExportColumn::make('updated_at')
                ->label('Last Updated')
                ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('Y-m-d H:i:s')),
        ];
    }

    /**
     * 🚀 Export Immediately (Disable Queuing)
     */
    public static function getDefaultShouldQueue(): bool
    {
        return false; // ✅ Runs export synchronously
    }

    /**
     * ✅ Custom Notification Message After Export
     */
    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = '✅ Your participant export is ready! ' . number_format($export->successful_rows) . ' '
              . str('row')->plural($export->successful_rows) . ' exported successfully.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ⚠️ However, ' . number_format($failedRowsCount) . ' '
                   . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
