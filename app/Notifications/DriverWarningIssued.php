<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverWarningIssued extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int    $warningNumber,
        private readonly string $reason,
        private readonly int    $visibilityScore,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        $penalty = match ($this->warningNumber) {
            1 => '-10 points de visibilité',
            2 => '-20 points de visibilité',
            default => 'suspension de 7 jours',
        };

        return (new FcmMessage)
            ->setTitle("Avertissement #{$this->warningNumber}")
            ->setBody("Vous avez reçu un avertissement : {$this->reason}. Conséquence : {$penalty}. Score de visibilité actuel : {$this->visibilityScore}/100.")
            ->setData([
                'type'            => 'driver_warning_issued',
                'warning_number'  => (string) $this->warningNumber,
                'reason'          => $this->reason,
                'visibility_score'=> (string) $this->visibilityScore,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'driver_warning_issued',
            'warning_number'   => $this->warningNumber,
            'reason'           => $this->reason,
            'visibility_score' => $this->visibilityScore,
        ];
    }
}
