<?php

namespace App\Notifications;

use App\Models\Withdrawal;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WithdrawalRequested extends Notification
{
    use Queueable;

    public function __construct(private readonly Withdrawal $withdrawal) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $amount    = number_format($this->withdrawal->amount, 0, '.', ' ');
        $provider  = strtoupper($this->withdrawal->provider);

        $title = '💳 Demande de retrait reçue';
        $body  = "Votre demande de retrait de {$amount} FCFA via {$provider} est en cours de traitement. Délai : 24h maximum.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'      => 'withdrawal_requested',
                    'reference' => $this->withdrawal->reference ?? '',
                    'amount'    => $this->withdrawal->amount,
                ]
            );
        }

        return [
            'type'      => 'withdrawal_requested',
            'title'     => $title,
            'body'      => $body,
            'reference' => $this->withdrawal->reference ?? '',
            'amount'    => $this->withdrawal->amount,
            'provider'  => $this->withdrawal->provider,
        ];
    }
}
