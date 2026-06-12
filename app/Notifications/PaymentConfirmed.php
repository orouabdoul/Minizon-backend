<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifie le passager que son paiement a été reçu et mis en escrow.
 */
class PaymentConfirmed extends Notification
{
    use Queueable;

    public function __construct(private readonly Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $booking = $this->payment->booking;
        $trip    = $booking?->trip;

        $title = '💰 Paiement confirmé';
        $body  = sprintf(
            'Votre paiement de %s XOF pour le trajet %s → %s a été reçu. Les fonds seront versés au conducteur après la course.',
            number_format($this->payment->gross_amount),
            $trip?->departure_city ?? '?',
            $trip?->arrival_city   ?? '?'
        );

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'         => 'payment_confirmed',
                    'payment_uuid' => $this->payment->uuid,
                    'trip_uuid'    => $trip?->uuid,
                    'amount'       => $this->payment->gross_amount,
                ]
            );
        }

        return [
            'type'         => 'payment_confirmed',
            'title'        => $title,
            'body'         => $body,
            'payment_uuid' => $this->payment->uuid,
            'trip_uuid'    => $trip?->uuid,
            'amount'       => $this->payment->gross_amount,
        ];
    }
}
