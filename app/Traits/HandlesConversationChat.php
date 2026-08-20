<?php

namespace App\Traits;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessage;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesConversationChat
{
    // -------------------------------------------------------------------------
    //  Ouvrir / récupérer la conversation d'une réservation (WhatsApp style)
    // -------------------------------------------------------------------------

    public function getOrCreate(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with('trip')->where('uuid', $uuid)->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        $userId      = $request->user()->id;
        $driverId    = $booking->trip?->user_id;
        $passengerId = $booking->passenger_id;

        if ($userId !== $driverId && $userId !== $passengerId) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        // Une seule conversation par paire driver–passager, quel que soit le nombre de réservations.
        $conversation = Conversation::whereHas('participants', fn ($q) => $q->where('users.id', $driverId))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $passengerId))
            ->latest('updated_at')
            ->first();

        if ($conversation) {
            $conversation->update([
                'booking_id' => $booking->id,
                'trip_id'    => $booking->trip_id,
            ]);
        } else {
            $conversation = Conversation::create([
                'booking_id' => $booking->id,
                'trip_id'    => $booking->trip_id,
            ]);
            $conversation->participants()->attach(array_filter([$driverId, $passengerId]));
        }

        return $this->apiResponse(true, 'Conversation prête.', [
            'conversation_uuid' => $conversation->uuid,
        ]);
    }

    // -------------------------------------------------------------------------
    //  Messages paginés + sync incrémentale (scroll infini + polling)
    // -------------------------------------------------------------------------

    public function messages(Request $request, string $uuid): JsonResponse
    {
        $userId       = $request->user()->id;
        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        if (! $conversation->hasParticipant($userId)) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $sinceId  = (int) $request->input('since_id', 0);
        $beforeId = (int) $request->input('before_id', 0);
        $perPage  = min((int) $request->input('per_page', 20), 50);

        // ── Mode sync incrémentale : since_id > 0 ────────────────────────────
        if ($sinceId > 0) {
            $raw = $conversation->messages()
                ->with('sender.profile')
                ->where('id', '>', $sinceId)
                ->orderBy('id')
                ->limit(100)
                ->get();

            $this->markDelivered($conversation, $userId, $raw);

            return $this->apiResponse(true, 'Messages.', [
                'items'     => $raw->map(fn ($m) => $this->formatChatMessage($m, $userId))->values(),
                'latest_id' => $raw->last()?->id ?? $sinceId,
                'has_more'  => false,
            ]);
        }

        // ── Mode historique paginé : before_id ───────────────────────────────
        $query = $conversation->messages()->with('sender.profile')->orderByDesc('id');

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $raw     = $query->limit($perPage)->get()->reverse()->values();
        $hasMore = $conversation->messages()
            ->where('id', '<', $raw->first()?->id ?? PHP_INT_MAX)
            ->exists();

        $this->markDelivered($conversation, $userId, $raw);

        return $this->apiResponse(true, 'Messages.', [
            'items'          => $raw->map(fn ($m) => $this->formatChatMessage($m, $userId))->values(),
            'has_more'       => $hasMore,
            'next_before_id' => $raw->first()?->id,
            'latest_id'      => $raw->last()?->id,
        ]);
    }

    // -------------------------------------------------------------------------
    //  Envoyer un message (texte ou fichier)
    // -------------------------------------------------------------------------

    public function send(Request $request, string $uuid): JsonResponse
    {
        $userId       = $request->user()->id;
        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        if (! $conversation->hasParticipant($userId)) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $validated = $request->validate([
            'body'                  => ['nullable', 'string', 'max:4000'],
            'reply_to_message_uuid' => ['nullable', 'string', 'uuid'],
            'attachment'            => ['nullable', 'file', 'max:25600', 'mimes:jpeg,png,webp,gif,pdf,doc,docx,mp3,m4a,aac,ogg,opus,wav,amr,webm,mp4'],
        ]);

        $hasText = ! empty(trim($validated['body'] ?? ''));
        $hasFile = $request->hasFile('attachment');

        if (! $hasText && ! $hasFile) {
            return $this->apiResponse(false, 'Le message ne peut pas être vide.', [], 422);
        }

        $replyToId = null;
        if (! empty($validated['reply_to_message_uuid'])) {
            $replyToId = Message::where('uuid', $validated['reply_to_message_uuid'])
                ->where('conversation_id', $conversation->id)
                ->value('id');
        }

        $attachmentPath = null;
        $attachmentType = null;

        if ($hasFile) {
            $file = $request->file('attachment');
            $mime = strtolower($file->getMimeType() ?? '');
            $ext  = strtolower($file->getClientOriginalExtension());

            // MIME check first; fallback to extension because .m4a → video/mp4, .webm → video/webm on many systems
            $attachmentType = match (true) {
                str_starts_with($mime, 'image/')                                                      => 'image',
                str_starts_with($mime, 'audio/')                                                      => 'audio',
                in_array($ext, ['mp3', 'm4a', 'aac', 'ogg', 'opus', 'wav', 'amr', 'webm', 'flac']) => 'audio',
                in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'])                        => 'image',
                default                                                                               => 'document',
            };
            $filename       = Str::uuid() . '.' . $ext;
            $attachmentPath = $file->storeAs('chat/' . $conversation->uuid, $filename, 'public');
        }

        $msg = DB::transaction(function () use ($conversation, $userId, $validated, $attachmentPath, $attachmentType, $hasText, $replyToId) {
            $msg = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $userId,
                'body'            => $hasText ? trim($validated['body']) : null,
                'reply_to_id'     => $replyToId,
                'attachment_path' => $attachmentPath,
                'attachment_type' => $attachmentType,
            ]);

            $conversation->touch();

            return $msg;
        });

        // Notification DB + push FCM aux autres participants via NewMessage
        $conversation->load('participants');
        $msg->load('sender.profile');
        $others = $conversation->participants->filter(fn ($u) => $u->id !== $userId);
        foreach ($others as $recipient) {
            try {
                $recipient->notify(new NewMessage($msg, $conversation));
            } catch (\Throwable) {}
        }

        return $this->apiResponse(true, 'Message envoyé.', $this->formatChatMessage($msg, $userId), 201);
    }

    // -------------------------------------------------------------------------
    //  Marquer tous les messages reçus comme lus
    // -------------------------------------------------------------------------

    public function markRead(Request $request, string $uuid): JsonResponse
    {
        $userId       = $request->user()->id;
        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation || ! $conversation->hasParticipant($userId)) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        // Récupérer les expéditeurs AVANT de marquer comme lus (double croche)
        $unreadSenderIds = $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->pluck('sender_id')
            ->unique();

        $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Push FCM aux expéditeurs → double croche côté expéditeur (WhatsApp style)
        if ($unreadSenderIds->isNotEmpty()) {
            $senderTokens = User::whereIn('id', $unreadSenderIds)
                ->whereNotNull('fcm_token')
                ->pluck('fcm_token')
                ->all();

            if (! empty($senderTokens)) {
                app(FcmService::class)->sendToMultiple(
                    $senderTokens,
                    '',
                    '',
                    ['type' => 'messages_read', 'conversation_uuid' => $conversation->uuid]
                );
            }
        }

        return $this->apiResponse(true, 'Messages marqués comme lus.');
    }

    // -------------------------------------------------------------------------
    //  Helper — marquer les messages entrants comme délivrés
    // -------------------------------------------------------------------------

    private function markDelivered(Conversation $conversation, int $userId, $messages): void
    {
        $undeliveredIds = $messages
            ->filter(fn ($m) => $m->sender_id !== $userId && $m->delivered_at === null)
            ->pluck('id');

        if ($undeliveredIds->isEmpty()) {
            return;
        }

        Message::whereIn('id', $undeliveredIds)->update(['delivered_at' => now()]);

        // Rafraîchir les instances en mémoire pour que formatChatMessage reflète delivered_at
        foreach ($messages as $m) {
            if ($undeliveredIds->contains($m->id)) {
                $m->delivered_at = now();
            }
        }

        // Push silencieux message_delivered à l'expéditeur
        $senderIds = $messages
            ->filter(fn ($m) => $undeliveredIds->contains($m->id))
            ->pluck('sender_id')
            ->unique();

        $tokens = User::whereIn('id', $senderIds)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->all();

        if (! empty($tokens)) {
            app(FcmService::class)->sendToMultiple($tokens, '', '', [
                'type'              => 'message_delivered',
                'conversation_uuid' => $conversation->uuid,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    //  Helper — formatage d'un message pour les réponses JSON
    // -------------------------------------------------------------------------

    private function formatChatMessage(Message $msg, int $myUserId): array
    {
        $tz         = 'Africa/Porto-Novo';
        $attachment = null;
        $attachType = $msg->attachment_type ?? 'document';

        if ($msg->attachment_path) {
            $attachment = [
                'url'  => Storage::disk('public')->url($msg->attachment_path),
                'type' => $attachType,
            ];
        }

        $messageType = $msg->body && $msg->attachment_path
            ? 'mixed'
            : ($msg->attachment_path ? $attachType : 'text');

        $textContent = $msg->body;

        return [
            'id'               => $msg->id,
            'uuid'             => $msg->uuid,
            'kind'             => $myUserId > 0 && $msg->sender_id === $myUserId ? 'outgoing' : 'incoming',
            'body'             => $textContent,
            'message'          => $textContent,
            'message_type'     => $messageType,
            'is_voice_message' => $attachType === 'audio' && $msg->attachment_path !== null,
            'is_delivered'     => $msg->delivered_at !== null,
            'is_read'          => $msg->read_at !== null,
            'is_edited'        => $msg->updated_at->gt($msg->created_at),
            'delivered_at'     => $msg->delivered_at?->toIso8601String(),
            'read_at'          => $msg->read_at?->toIso8601String(),
            'time'             => $msg->created_at->setTimezone($tz)->format('H:i'),
            'raw_date'         => $msg->created_at->setTimezone($tz)->format('Y-m-d'),
            'reply_to_id'      => $msg->reply_to_id,
            'attachment'       => $attachment,
        ];
    }
}
