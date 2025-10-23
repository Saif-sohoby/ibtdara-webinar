<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Participant extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'tags', // Tags array
        'sources', // New field for social media sources
    ];

    protected $casts = [
        'tags' => 'array', // Cast tags as an array
        'sources' => 'array', // Cast sources as an array
    ];

    public function webinars()
    {
        return $this->belongsToMany(Webinar::class)->withTimestamps();
    }

    public function applyRegisteredTagsForWebinar(Webinar $webinar): void
    {
        $webinarTags = is_array($webinar->tags) ? $webinar->tags : (json_decode($webinar->tags, true) ?: []);

        $registeredTags = array_map(fn($tag) => $tag . '_registered', $webinarTags);

        if (empty($registeredTags)) {
            $registeredTags[] = 'registered';
        }

        $participantTags = is_array($this->tags) ? $this->tags : (json_decode($this->tags, true) ?: []);

        $this->tags = array_unique(array_merge($participantTags, $registeredTags));
        $this->save();
    }

    public function generateJoinLink(Webinar $webinar)
    {
        $uniqueCode = md5($this->id . '-' . $webinar->id . '-' . now());

        $this->update(['join_code' => $uniqueCode]);

        return url("/join/{$uniqueCode}");
    }
}
