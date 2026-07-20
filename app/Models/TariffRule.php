<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TariffRule extends Model
{
    protected $table = 'tariff_rules';

    protected $fillable = [
        'uuid', 'key', 'name', 'description', 'value', 'unit', 'active',
    ];

    protected $casts = [
        'value'  => 'float',
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            $m->uuid ??= (string) Str::uuid();
        });
    }
}
