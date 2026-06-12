<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewMessage extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Message      $message,
        private readonly Conversation $conversation,
    ) {}

    public function via(object $notifiable): array
    {
        return [];
    }

    public function toArray(object $notifiable): array
    {
        $sender     = $this->message->sender;
        $senderName = $sender->profile
            ? trim("{$sender->profile->first_name} {$sender->profile->last_name}")
            : $sender->phone;

        $preview = $this->message->body
            ? (strlen($this->message->body) > 60 ? substr($this->message->body, 0, 57) . '...' : $this->message->body)
            : '📷 Image';

        /** @var \App\Models\User $notifiable */
        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $senderName,
                $preview,
                [
                    'type'              => 'new_message',
                    'conversation_uuid' => $this->conversation->uuid,
                    'message_id'        => (string) $this->message->id,
                ]
            );
        }

        return [];
    }
}
