<?php

namespace App\Traits;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
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
    //  Messages paginés (scroll infini)
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

        $perPage  = min((int) $request->input('per_page', 20), 50);
        $beforeId = (int) $request->input('before_id', 0);

        $query = $conversation->messages()->with('sender.profile')->orderByDesc('id');

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $raw     = $query->limit($perPage)->get()->reverse()->values();
        $hasMore = $conversation->messages()
            ->where('id', '<', $raw->first()?->id ?? PHP_INT_MAX)
            ->exists();

        return $this->apiResponse(true, 'Messages.', [
            'items'          => $raw->map(fn ($m) => $this->formatChatMessage($m, $userId))->values(),
            'has_more'       => $hasMore,
            'next_before_id' => $raw->first()?->id,
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
            'body'       => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpeg,png,webp,gif,pdf,doc,docx'],
        ]);

        $hasText = ! empty(trim($validated['body'] ?? ''));
        $hasFile = $request->hasFile('attachment');

        if (! $hasText && ! $hasFile) {
            return $this->apiResponse(false, 'Le message ne peut pas être vide.', [], 422);
        }

        $attachmentPath = null;
        $attachmentType = null;

        if ($hasFile) {
            $file           = $request->file('attachment');
            $mime           = $file->getMimeType() ?? '';
            $attachmentType = str_starts_with($mime, 'image/') ? 'image' : 'document';
            $ext            = $file->getClientOriginalExtension();
            $filename       = Str::uuid() . '.' . $ext;
            $attachmentPath = $file->storeAs('chat/' . $conversation->uuid, $filename, 'public');
        }

        $msg = DB::transaction(function () use ($conversation, $userId, $validated, $attachmentPath, $attachmentType, $hasText) {
            $msg = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $userId,
                'body'            => $hasText ? trim($validated['body']) : null,
                'attachment_path' => $attachmentPath,
                'attachment_type' => $attachmentType,
            ]);

            $conversation->touch();

            return $msg;
        });

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

        $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->apiResponse(true, 'Messages marqués comme lus.');
    }

    // -------------------------------------------------------------------------
    //  Helper — formatage d'un message pour les réponses JSON
    // -------------------------------------------------------------------------

    private function formatChatMessage(Message $msg, int $myUserId): array
    {
        $tz         = 'Africa/Porto-Novo';
        $attachment = null;

        if ($msg->attachment_path) {
            $attachment = [
                'url'  => Storage::disk('public')->url($msg->attachment_path),
                'type' => $msg->attachment_type ?? 'image',
            ];
        }

        return [
            'id'         => $msg->id,
            'uuid'       => $msg->uuid,
            'kind'       => $myUserId > 0 && $msg->sender_id === $myUserId ? 'outgoing' : 'incoming',
            'body'       => $msg->body,
            'time'       => $msg->created_at->setTimezone($tz)->format('H:i'),
            'raw_date'   => $msg->created_at->setTimezone($tz)->format('Y-m-d'),
            'read_at'    => $msg->read_at?->toIso8601String(),
            'attachment' => $attachment,
        ];
    }
}
