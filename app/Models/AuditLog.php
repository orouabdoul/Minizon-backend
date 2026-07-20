<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'action_type',
        'severity',
        'description',
        'target_type',
        'target_name',
        'ip_address',
        'user_agent',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Enregistrement d'une action admin dans le journal.
     *
     * @param string      $action      Clé technique (ex: 'driver.approved')
     * @param string|null $actionType  Type UI (ex: 'approbation_conducteur')
     * @param string      $severity    'info' | 'avertissement' | 'critique'
     * @param string|null $description Texte lisible affiché dans l'UI
     * @param string|null $targetType  Type de la cible ('user', 'trip'…)
     * @param string|null $targetName  Nom affiché de la cible
     */
    public static function record(
        string  $action,
        ?int    $userId      = null,
        string  $ip          = '—',
        ?array  $payload     = null,
        ?string $userAgent   = null,
        ?string $actionType  = null,
        string  $severity    = 'info',
        ?string $description = null,
        ?string $targetType  = null,
        ?string $targetName  = null,
    ): self {
        return static::create([
            'user_id'     => $userId,
            'action'      => $action,
            'action_type' => $actionType,
            'severity'    => $severity,
            'description' => $description,
            'target_type' => $targetType,
            'target_name' => $targetName,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
            'payload'     => $payload,
            'created_at'  => now(),
        ]);
    }
}
