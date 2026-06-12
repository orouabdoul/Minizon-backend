<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'ip_address', 'user_agent', 'payload', 'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?int $userId, string $ip, ?array $payload = null, ?string $userAgent = null): self
    {
        return static::create([
            'user_id'    => $userId,
            'action'     => $action,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'payload'    => $payload,
            'created_at' => now(),
        ]);
    }
}
