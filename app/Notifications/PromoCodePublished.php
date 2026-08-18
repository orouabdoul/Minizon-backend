<?php

namespace App\Notifications;

use App\Models\PromoCode;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PromoCodePublished extends Notification
{
    use Queueable;

    public function __construct(private readonly PromoCode $promo) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $expires = $this->promo->expires_at?->format('d/m/Y') ?? '';
        $title   = '🎁 Nouveau code promo disponible !';
        $body    = "Utilisez le code {$this->promo->code} pour obtenir {$this->promo->discount}% de réduction. Valable jusqu'au {$expires}.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'     => 'promo_published',
                    'code'     => $this->promo->code,
                    'discount' => $this->promo->discount,
                ]
            );
        }

        return [
            'type'        => 'promo_published',
            'title'       => $title,
            'body'        => $body,
            'code'        => $this->promo->code,
            'discount'    => $this->promo->discount,
            'expires_at'  => $this->promo->expires_at?->toDateString(),
            'description' => $this->promo->description,
        ];
    }
}
