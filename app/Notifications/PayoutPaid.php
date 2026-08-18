<?php

namespace App\Notifications;

use App\Models\DriverPayout;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PayoutPaid extends Notification
{
    use Queueable;

    public function __construct(private readonly DriverPayout $payout) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $amount = number_format($this->payout->net_amount, 0, ',', ' ');
        $method = $this->payout->method;
        $title  = '💸 Virement reçu !';
        $body   = "{$amount} FCFA ont été versés sur votre compte via {$method}.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'       => 'payout_paid',
                    'payout_id'  => $this->payout->id,
                    'net_amount' => $this->payout->net_amount,
                ]
            );
        }

        return [
            'type'       => 'payout_paid',
            'title'      => $title,
            'body'       => $body,
            'payout_id'  => $this->payout->id,
            'net_amount' => $this->payout->net_amount,
            'method'     => $method,
        ];
    }
}
