<?php

namespace App\Notifications;

use App\Models\Withdrawal;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifie le conducteur du résultat de sa demande de retrait.
 */
class WithdrawalProcessed extends Notification
{
    use Queueable;

    public function __construct(private readonly Withdrawal $withdrawal) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $status  = $this->withdrawal->status;
        $amount  = number_format($this->withdrawal->amount);
        $provider = strtoupper($this->withdrawal->provider);

        if ($status === 'approved') {
            $title = '✅ Virement envoyé !';
            $body  = "{$amount} XOF ont été envoyés vers votre numéro {$provider}. Vérifiez votre solde Mobile Money.";
        } else {
            $title = '❌ Retrait refusé';
            $body  = "Votre demande de {$amount} XOF a été refusée. Motif : " . ($this->withdrawal->failed_reason ?? 'non précisé');
        }

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'            => 'withdrawal_processed',
                    'withdrawal_id'   => $this->withdrawal->id,
                    'withdrawal_status' => $status,
                    'amount'          => $this->withdrawal->amount,
                ]
            );
        }

        return [
            'type'              => 'withdrawal_processed',
            'title'             => $title,
            'body'              => $body,
            'withdrawal_id'     => $this->withdrawal->id,
            'withdrawal_status' => $status,
            'amount'            => $this->withdrawal->amount,
        ];
    }
}
