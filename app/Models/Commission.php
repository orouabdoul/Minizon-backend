<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'type', 'label', 'rate_percent', 'status'];

    protected $casts = [
        'rate_percent' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            $c->uuid ??= (string) Str::uuid();
        });
    }

    public function isActive(): bool { return $this->status === 'active'; }
}
