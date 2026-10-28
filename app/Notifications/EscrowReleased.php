<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EscrowReleased extends Notification
{
    use Queueable;

    public function __construct(private readonly Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $amount = number_format($this->payment->net_amount, 0, ',', ' ');
        $title  = 'Fonds disponibles !';
        $body   = "{$amount} FCFA ont été crédités sur votre portefeuille. Vous pouvez effectuer un retrait dès maintenant.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'       => 'escrow_released',
                    'payment_id' => $this->payment->id,
                    'net_amount' => $this->payment->net_amount,
                ]
            );
        }

        return [
            'type'       => 'escrow_released',
            'title'      => $title,
            'body'       => $body,
            'payment_id' => $this->payment->id,
            'net_amount' => $this->payment->net_amount,
        ];
    }
}
