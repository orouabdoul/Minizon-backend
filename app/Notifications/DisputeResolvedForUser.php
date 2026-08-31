<?php

namespace App\Notifications;

use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifie le conducteur ou passager de la résolution finale d'un litige.
 *
 * $outcome : 'refunded'         → remboursement au passager (conducteur perd les fonds)
 *            'paid_to_driver'   → fonds libérés au conducteur (litige rejeté)
 *            'partial_refund'   → remboursement partiel
 */
class DisputeResolvedForUser extends Notification
{
    use Queueable;

    public function __construct(
        private readonly mixed  $dispute,
        private readonly string $outcome,   // refunded | paid_to_driver | partial_refund
        private readonly string $recipient, // passenger | driver
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        [$title, $body] = $this->buildMessage();

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'       => 'dispute_resolved',
                    'outcome'    => $this->outcome,
                    'recipient'  => $this->recipient,
                    'dispute_id' => (string) $this->dispute->id,
                ]
            );
        }

        return [
            'type'       => 'dispute_resolved',
            'title'      => $title,
            'body'       => $body,
            'outcome'    => $this->outcome,
            'recipient'  => $this->recipient,
            'dispute_id' => $this->dispute->id,
        ];
    }

    private function buildMessage(): array
    {
        if ($this->recipient === 'passenger') {
            return match ($this->outcome) {
                'refunded'       => ['✅ Litige résolu — Remboursement', 'Votre litige a été résolu en votre faveur. Le remboursement est en cours de traitement.'],
                'paid_to_driver' => ['ℹ️ Litige clôturé', 'Après examen, votre litige a été clôturé. Les fonds ont été libérés au conducteur.'],
                default          => ['✅ Litige résolu', 'Votre litige a été traité et résolu par notre équipe.'],
            };
        }

        // recipient === driver
        return match ($this->outcome) {
            'paid_to_driver' => ['✅ Litige résolu en votre faveur', 'Le litige a été examiné et clôturé. Vos gains sont libérés.'],
            'refunded'       => ['ℹ️ Litige clôturé', "Le litige a été résolu. Les fonds ont été remboursés au passager après examen de notre équipe."],
            default          => ['ℹ️ Litige clôturé', 'Le litige concernant votre trajet a été traité par notre équipe.'],
        };
    }
}
