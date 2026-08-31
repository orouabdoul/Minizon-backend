<?php

namespace App\Models;

use App\Services\FcmService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdminNotification extends Model
{
    use HasFactory;

    protected $table = 'admin_notifications';

    protected $fillable = [
        'uuid',
        'type',
        'priority',
        'status',
        'title',
        'description',
        'ref_type',
        'ref_id',
        'user_id',
        'read_at',
        'handled_at',
    ];

    protected $casts = [
        'read_at'    => 'datetime',
        'handled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $n) {
            $n->uuid ??= (string) Str::uuid();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isUnread(): bool  { return $this->status === 'unread'; }
    public function isHandled(): bool { return $this->status === 'handled'; }

    /**
     * Crée une notification admin (table admin_notifications) ET envoie un push FCM à tous les admins.
     *
     * @param  string       $type        system | user | payment | dispute | driver | vehicle | support
     * @param  string       $priority    urgent | high | normal | low
     * @param  string       $title
     * @param  string       $description
     * @param  string|null  $refType     booking | payment | dispute | trip | user | vehicle | withdrawal | support
     * @param  string|null  $refId       UUID ou ID de la ressource liée
     * @param  int|null     $userId      ID de l'utilisateur concerné (pour afficher son profil dans l'UI admin)
     */
    public static function notifyAdmins(
        string  $type,
        string  $priority,
        string  $title,
        string  $description,
        ?string $refType = null,
        ?string $refId   = null,
        ?int    $userId  = null,
    ): void {
        try {
            // Stocker dans admin_notifications
            self::create([
                'type'        => $type,
                'priority'    => $priority,
                'status'      => 'unread',
                'title'       => $title,
                'description' => $description,
                'ref_type'    => $refType,
                'ref_id'      => $refId,
                'user_id'     => $userId,
            ]);

            // Envoyer push FCM à tous les admins avec token
            $adminRoleId = Role::where('name', 'admin')->value('id');
            if (! $adminRoleId) return;

            $tokens = User::where('role_id', $adminRoleId)
                ->whereNotNull('fcm_token')
                ->where('is_blocked', false)
                ->pluck('fcm_token')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (! empty($tokens)) {
                app(FcmService::class)->sendToMultiple($tokens, $title, $description, [
                    'type'     => $type,
                    'ref_type' => $refType ?? '',
                    'ref_id'   => $refId   ?? '',
                ]);
            }

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AdminNotification::notifyAdmins failed', [
                'error' => $e->getMessage(),
                'type'  => $type,
                'title' => $title,
            ]);
        }
    }
}
