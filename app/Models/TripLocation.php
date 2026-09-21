<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripLocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['trip_id', 'lat', 'lng', 'speed', 'heading', 'recorded_at'];

    protected $casts = [
        'lat'         => 'float',
        'lng'         => 'float',
        'speed'       => 'float',
        'heading'     => 'float',
        'recorded_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
