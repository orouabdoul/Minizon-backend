<?php

namespace App\Notifications;

use App\Models\Vehicle;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VehicleStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Vehicle $vehicle,
        private readonly string  $status, // approved | rejected | suspended
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $name = $this->vehicle->brand . ' ' . $this->vehicle->model;

        $messages = [
            'approved'  => [
                'title' => '✅ Véhicule approuvé !',
                'body'  => "Votre {$name} a été validé. Vous pouvez maintenant publier des trajets.",
            ],
            'rejected'  => [
                'title' => '❌ Véhicule refusé',
                'body'  => "Votre {$name} n'a pas été validé." . ($this->vehicle->rejection_reason ? " Motif : {$this->vehicle->rejection_reason}" : ' Vérifiez vos documents et resoumettre.'),
            ],
            'suspended' => [
                'title' => '⚠️ Véhicule suspendu',
                'body'  => "Votre {$name} a été suspendu temporairement." . ($this->vehicle->rejection_reason ? " Motif : {$this->vehicle->rejection_reason}" : ''),
            ],
        ];

        $msg = $messages[$this->status] ?? [
            'title' => 'Mise à jour véhicule',
            'body'  => "Le statut de votre {$name} a changé : {$this->status}.",
        ];

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $msg['title'],
                $msg['body'],
                [
                    'type'       => 'vehicle_status',
                    'vehicle_id' => (string) $this->vehicle->id,
                    'status'     => $this->status,
                ]
            );
        }

        return [
            'type'       => 'vehicle_status',
            'title'      => $msg['title'],
            'body'       => $msg['body'],
            'vehicle_id' => $this->vehicle->id,
            'status'     => $this->status,
        ];
    }
}
