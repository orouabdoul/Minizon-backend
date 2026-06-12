<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Penalty extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'reason', 'points_added', 'financial_fine',
    ];

    protected $casts = [
        'points_added'   => 'integer',
        'financial_fine' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
