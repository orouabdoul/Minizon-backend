<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    // =========================================================================
    //  POST /api/passenger/bookings/{uuid}/conversation
    //  POST /api/driver/bookings/{uuid}/conversation
    //  Ouvrir / récupérer la conversation d'une réservation (WhatsApp style)
    // =========================================================================

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

        // WhatsApp style : une seule conversation par paire driver–passager,
        // indépendamment du nombre de réservations.
        $conversation = Conversation::whereHas('participants', fn ($q) => $q->where('users.id', $driverId))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $passengerId))
            ->latest('updated_at')
            ->first();

        if ($conversation) {
            // Mettre à jour le contexte (booking/trip courant) sans changer l'historique
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

    // =========================================================================
    //  GET /api/passenger/conversations/{uuid}/messages
    //  GET /api/driver/conversations/{uuid}/messages
    //  Messages paginés d'une conversation (scroll infini)
    // =========================================================================

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
            'items'          => $raw->map(fn ($m) => $this->formatMessage($m, $userId))->values(),
            'has_more'       => $hasMore,
            'next_before_id' => $raw->first()?->id,
        ]);
    }

    // =========================================================================
    //  POST /api/passenger/conversations/{uuid}/messages
    //  POST /api/driver/conversations/{uuid}/messages
    //  Envoyer un message texte ou un fichier (image / document)
    // =========================================================================

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

        return $this->apiResponse(true, 'Message envoyé.', $this->formatMessage($msg, $userId), 201);
    }

    // =========================================================================
    //  POST /api/passenger/conversations/{uuid}/read
    //  POST /api/driver/conversations/{uuid}/read
    //  Marquer tous les messages reçus comme lus
    // =========================================================================

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

    // =========================================================================
    //  ADMIN — Modération
    // =========================================================================

    public function adminIndex(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $conversations = Conversation::with(['participants.profile', 'lastMessage', 'trip'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return $this->apiResponse(true, 'Conversations.', [
            'items' => collect($conversations->items())->map(
                fn ($c) => $this->formatConversation($c, 0)
            ),
            'total' => $conversations->total(),
            'page'  => $conversations->currentPage(),
        ]);
    }

    public function adminMessages(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        $messages = $conversation->messages()
            ->with('sender.profile')
            ->orderBy('id')
            ->get()
            ->map(fn ($m) => $this->formatMessage($m, 0));

        return $this->apiResponse(true, 'Messages.', ['items' => $messages]);
    }

    public function adminDeleteMessage(Request $request, string $uuid, int $id): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        $message = $conversation->messages()->find($id);

        if (! $message) {
            return $this->apiResponse(false, 'Message introuvable.', [], 404);
        }

        if ($message->attachment_path) {
            Storage::disk('public')->delete($message->attachment_path);
        }

        $message->delete();

        return $this->apiResponse(true, 'Message supprimé.');
    }

    public function adminDelete(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        $conversation->messages()
            ->whereNotNull('attachment_path')
            ->each(fn ($m) => Storage::disk('public')->delete($m->attachment_path));

        $conversation->delete();

        return $this->apiResponse(true, 'Conversation supprimée.');
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function formatMessage(Message $msg, int $myUserId): array
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

    private function formatConversation(Conversation $conv, int $myUserId): array
    {
        $other = $myUserId > 0
            ? $conv->participants->first(fn ($p) => $p->id !== $myUserId)
            : $conv->participants->first();

        $profile = $other?->profile;
        $last    = $conv->lastMessage;
        $tz      = 'Africa/Porto-Novo';

        $lastText = $last?->body
            ?? ($last?->attachment_path
                ? ($last->attachment_type === 'image' ? '📷 Photo' : '📄 Document')
                : null);

        return [
            'uuid'         => $conv->uuid,
            'other_name'   => $profile
                ? trim("{$profile->first_name} {$profile->last_name}")
                : ($other?->phone ?? '—'),
            'last_message' => $lastText,
            'last_time'    => $last?->created_at->setTimezone($tz)->format('H:i'),
            'unread_count' => $myUserId > 0 ? $conv->unreadCountFor($myUserId) : 0,
            'trip_route'   => $conv->trip
                ? ($conv->trip->departure_city . ' → ' . $conv->trip->arrival_city)
                : null,
        ];
    }
}
