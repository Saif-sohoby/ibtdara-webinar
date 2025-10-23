<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;


class Webinar extends Model
{
    protected $fillable = [
        'topic',
        'start_time',
        'end_time',
        'duration',
        'zoho_webinar_id',
        'instance_id',
        'registration_link',
        'start_link',
        'registration_count',
        'webinar_type',
        'tags',
        'stream_link',
        'reminder_offsets',
        'thumbnail',
    ];


    protected $casts = [
        'tags' => 'array', // Cast tags as an array
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'reminder_offsets' => 'array', // Add this line
    ];

    public function participants()
    {
        return $this->belongsToMany(Participant::class)
            ->using(WebinarParticipant::class)
            ->withPivot(['webinar_id']) // Ensure pivot fields are loaded
            ->withTimestamps();
    }

    public function setDurationAttribute()
    {
        if ($this->start_time && $this->end_time) {
            $this->attributes['duration'] = $this->start_time->diffInMinutes($this->end_time);
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($webinar) {
            $webinar->registration_link = url('/register/' . Str::uuid());
        });
    }

    public function getThumbnailUrlAttribute()
    {
        if (!$this->thumbnail) {
            return null;
        }

        return config('app.url') . '/storage/' . ltrim($this->thumbnail, '/');
    }
}
