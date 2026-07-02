<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'trip_id',
        'reviewer_id',
        'reviewee_id',
        'rating',
        'comment',
        'driver_reply',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $review) {
            if (empty($review->uuid)) {
                $review->uuid = (string) Str::uuid();
            }
        });
    }

    // -----------------------------------------------------------------------
    // RELATIONS
    // -----------------------------------------------------------------------

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewee()
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }
}
