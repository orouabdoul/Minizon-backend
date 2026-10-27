<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverWarned extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $reason,
        private readonly int    $warningCount,
        private readonly int    $visibilityScore,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        $ordinal = match ($this->warningCount) {
            1 => '1er',
            2 => '2ème',
            default => "{$this->warningCount}ème",
        };

        return (new FcmMessage)
            ->setTitle("Avertissement ({$ordinal})")
            ->setBody("Vous avez reçu un avertissement : {$this->reason}. Votre visibilité est maintenant de {$this->visibilityScore}%.")
            ->setData([
                'type'            => 'driver_warned',
                'warning_count'   => (string) $this->warningCount,
                'visibility_score'=> (string) $this->visibilityScore,
                'reason'          => $this->reason,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'driver_warned',
            'warning_count'    => $this->warningCount,
            'visibility_score' => $this->visibilityScore,
            'reason'           => $this->reason,
        ];
    }
}
