<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $conversations = Conversation::with(['participants.profile', 'lastMessage', 'trip'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        $tz = 'Africa/Porto-Novo';

        return $this->apiResponse(true, 'Conversations.', [
            'items' => collect($conversations->items())->map(function (Conversation $conv) use ($tz) {
                $other   = $conv->participants->first();
                $profile = $other?->profile;
                $last    = $conv->lastMessage;

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
                    'unread_count' => 0,
                    'trip_route'   => $conv->trip
                        ? ($conv->trip->departure_city . ' → ' . $conv->trip->arrival_city)
                        : null,
                ];
            }),
            'total' => $conversations->total(),
            'page'  => $conversations->currentPage(),
        ]);
    }

    public function messages(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        $tz       = 'Africa/Porto-Novo';
        $messages = $conversation->messages()
            ->with('sender.profile')
            ->orderBy('id')
            ->get()
            ->map(fn (Message $m) => [
                'id'         => $m->id,
                'uuid'       => $m->uuid,
                'kind'       => 'incoming',
                'body'       => $m->body,
                'time'       => $m->created_at->setTimezone($tz)->format('H:i'),
                'raw_date'   => $m->created_at->setTimezone($tz)->format('Y-m-d'),
                'read_at'    => $m->read_at?->toIso8601String(),
                'attachment' => $m->attachment_path ? [
                    'url'  => Storage::disk('public')->url($m->attachment_path),
                    'type' => $m->attachment_type ?? 'image',
                ] : null,
            ]);

        return $this->apiResponse(true, 'Messages.', ['items' => $messages]);
    }

    public function deleteMessage(Request $request, string $uuid, int $id): JsonResponse
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

    public function destroy(Request $request, string $uuid): JsonResponse
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
            ->each(fn (Message $m) => Storage::disk('public')->delete($m->attachment_path));

        $conversation->delete();

        return $this->apiResponse(true, 'Conversation supprimée.');
    }
}
