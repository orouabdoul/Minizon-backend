<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverSuspended extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $reason,
        private readonly string $suspendedUntil,
        private readonly int    $durationDays,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage)
            ->setTitle('Compte suspendu')
            ->setBody("Votre compte a été suspendu pour {$this->durationDays} jour(s) : {$this->reason}. Suspension levée le {$this->suspendedUntil}.")
            ->setData([
                'type'           => 'driver_suspended',
                'reason'         => $this->reason,
                'suspended_until'=> $this->suspendedUntil,
                'duration_days'  => (string) $this->durationDays,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'driver_suspended',
            'reason'         => $this->reason,
            'suspended_until'=> $this->suspendedUntil,
            'duration_days'  => $this->durationDays,
        ];
    }
}
