<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentExpiringSoon extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $documentLabel,
        private readonly int    $daysLeft,
        private readonly string $expiryDate,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        $urgency = $this->daysLeft <= 7 ? 'URGENT — ' : '';

        return (new FcmMessage)
            ->setTitle("{$urgency}Document bientôt expiré")
            ->setBody("{$this->documentLabel} expire dans {$this->daysLeft} jour(s) (le {$this->expiryDate}). Mettez-le à jour avant qu'il expire.")
            ->setData([
                'type'           => 'document_expiring_soon',
                'document_label' => $this->documentLabel,
                'days_left'      => (string) $this->daysLeft,
                'expiry_date'    => $this->expiryDate,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'document_expiring_soon',
            'document_label' => $this->documentLabel,
            'days_left'      => $this->daysLeft,
            'expiry_date'    => $this->expiryDate,
        ];
    }
}
