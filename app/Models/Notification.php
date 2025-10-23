<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class Notification extends DatabaseNotification
{
    protected $table = 'notifications';

    // Casts (Laravel default 'data' already array hota hai, hum extra_data add kar rahe)
    protected $casts = [
        'data'      => 'array',
        'read_at'   => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Ensure UUID when creating manually from Filament
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
