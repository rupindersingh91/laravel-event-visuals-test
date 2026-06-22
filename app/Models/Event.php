<?php

namespace App\Models;

use App\Support\CityAnchors;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function getLocationNameAttribute(): string
    {
        return CityAnchors::nearest((float) $this->latitude, (float) $this->longitude);
    }

    /** @return list<string> */
    public function getImageUrlsAttribute(): array
    {
        $files = [
            'event-1.jpg', 'event-2.jpg', 'event-3.jpg',
            'event-4.jpg', 'event-5.jpg', 'event-6.jpg',
            'event-7.jpg', 'event-8.jpg', 'event-9.jpg',
        ];
        $n = count($files);
        $h = abs(crc32(((string) ($this->type ?? '')) . ((string) ($this->id ?? ''))));
        $count = 2 + ($h % 2);
        $urls = [];
        for ($i = 0; $i < $count; $i++) {
            $urls[] = '/images/events/' . $files[($h + $i * 3) % $n];
        }

        return array_values(array_unique($urls)) ?: ['/images/events/event-1.jpg'];
    }

    public function getStartsAtIsoAttribute(): string
    {
        return Carbon::createFromTimestamp((int) $this->created_time)->toIso8601String();
    }
}
