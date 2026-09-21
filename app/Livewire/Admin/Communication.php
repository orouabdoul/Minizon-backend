<?php

namespace App\Livewire\Admin;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\FcmService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Communication extends Component
{
    use WithFileUploads;

    // ── Filtres liste ─────────────────────────────────────────
    public string $search     = '';
    public string $roleFilter = '';   // '' | 'driver' | 'passenger'

    // ── Conversation active ────────────────────────────────────
    public ?int $selectedId = null;

    // ── Composer un message ───────────────────────────────────
    public string $newMessage = '';
    public $attachment        = null;

    // ── Nouveau message (recherche utilisateur) ───────────────
    public bool   $showCompose   = false;
    public string $composeSearch = '';
    public string $composeRole   = '';

    // ── Diffusion ─────────────────────────────────────────────
    public bool   $showBroadcast    = false;
    public string $broadcastMessage = '';
    public string $broadcastTarget  = 'tous';

    // ── Flash notification ────────────────────────────────────
    public ?string $flash     = null;
    public ?string $flashType = null;

    public function updatingSearch(): void     { $this->selectedId = null; }
    public function updatingRoleFilter(): void { $this->selectedId = null; }

    // ─────────────────────────────────────────────────────────
    //  Ouvrir / fermer une conversation
    // ─────────────────────────────────────────────────────────

    public function view(int $id): void
    {
        $this->selectedId = $id;
        $adminId = $this->getAdminUserId();

        if ($adminId) {
            Message::where('conversation_id', $id)
                ->where('sender_id', '!=', $adminId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        $this->dispatch('chat-opened');
    }

    public function closeView(): void
    {
        $this->selectedId = null;
        $this->newMessage  = '';
        $this->attachment  = null;
    }

    // ─────────────────────────────────────────────────────────
    //  Envoyer un message (texte + fichier + audio)
    // ─────────────────────────────────────────────────────────

    public function sendMessage(): void
    {
        $this->validate([
            'newMessage' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|max:25600|mimes:jpeg,png,webp,gif,pdf,doc,docx,mp3,m4a,aac,ogg,opus,wav,amr,webm,mp4',
        ]);

        $hasText = trim($this->newMessage) !== '';
        $hasFile = $this->attachment !== null;
        if (! $hasText && ! $hasFile) return;

        $adminId = $this->getAdminUserId();
        if (! $adminId) {
            $this->flash = "Aucun utilisateur admin trouvé dans la table users. Créez d'abord un compte user avec le rôle 'admin'.";
            $this->flashType = 'error';
            return;
        }

        $conversation = Conversation::with('participants')->findOrFail($this->selectedId);

        $attachmentPath = null;
        $attachmentType = null;

        if ($hasFile) {
            $ext  = strtolower($this->attachment->getClientOriginalExtension());
            $mime = strtolower($this->attachment->getMimeType() ?? '');

            $attachmentType = match (true) {
                str_starts_with($mime, 'image/')                                                      => 'image',
                str_starts_with($mime, 'audio/')                                                      => 'audio',
                in_array($ext, ['mp3', 'm4a', 'aac', 'ogg', 'opus', 'wav', 'amr', 'webm', 'flac']) => 'audio',
                in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])                                 => 'image',
                default                                                                               => 'document',
            };

            $filename       = Str::uuid() . '.' . $ext;
            $attachmentPath = $this->attachment->storeAs('chat/' . $conversation->uuid, $filename, 'public');
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $adminId,
            'body'            => $hasText ? trim($this->newMessage) : null,
            'attachment_path' => $attachmentPath,
            'attachment_type' => $attachmentType,
        ]);

        $conversation->touch();

        $bodyText = $hasText ? trim($this->newMessage) : match ($attachmentType) {
            'audio'    => '🎙️ Message vocal',
            'image'    => '📷 Photo',
            default    => '📄 Document',
        };

        $tokens = $conversation->participants
            ->filter(fn ($u) => $u->id !== $adminId)
            ->pluck('fcm_token')->filter()->values()->all();

        if (! empty($tokens)) {
            app(FcmService::class)->sendToMultiple($tokens, 'Minizon Admin', $bodyText, [
                'type' => 'new_message', 'conversation_uuid' => $conversation->uuid,
            ]);
        }

        $this->newMessage = '';
        $this->attachment = null;
        $this->dispatch('chat-opened');
    }

    // ─────────────────────────────────────────────────────────
    //  Démarrer / retrouver une conversation
    // ─────────────────────────────────────────────────────────

    public function startConversation(string $userUuid): void
    {
        $adminId = $this->getAdminUserId();
        if (! $adminId) return;

        $targetUser = User::where('uuid', $userUuid)->first();
        if (! $targetUser) return;

        $conv = Conversation::whereNull('trip_id')
            ->whereNull('booking_id')
            ->whereHas('participants', fn ($q) => $q->where('users.id', $adminId))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $targetUser->id))
            ->first();

        if (! $conv) {
            $conv = Conversation::create(['type' => 'support', 'trip_id' => null, 'booking_id' => null]);
            $conv->participants()->attach([$adminId, $targetUser->id]);
        }

        $this->selectedId    = $conv->id;
        $this->showCompose   = false;
        $this->composeSearch = '';
        $this->dispatch('chat-opened');
    }

    // ─────────────────────────────────────────────────────────
    //  Diffusion
    // ─────────────────────────────────────────────────────────

    public function sendBroadcast(): void
    {
        $this->validate([
            'broadcastMessage' => 'required|string|max:2000',
            'broadcastTarget'  => 'required|in:tous,tous_conducteurs,tous_passagers,en_ligne,en_trajet',
        ]);

        $adminId = $this->getAdminUserId();
        if (! $adminId) return;

        $usersQuery = User::where('is_blocked', false)->where('id', '!=', $adminId);

        match ($this->broadcastTarget) {
            'tous_conducteurs' => $usersQuery->whereHas('role', fn ($q) => $q->where('name', 'driver')),
            'tous_passagers'   => $usersQuery->whereHas('role', fn ($q) => $q->where('name', 'passenger')),
            'en_ligne'         => $usersQuery->whereHas('role', fn ($q) => $q->where('name', 'driver'))->where('is_online', true),
            'en_trajet'        => $usersQuery->whereHas('role', fn ($q) => $q->where('name', 'driver'))
                                             ->whereHas('trips', fn ($q) => $q->where('status', 'active')),
            default            => $usersQuery->whereHas('role', fn ($q) => $q->whereIn('name', ['driver', 'passenger'])),
        };

        $users     = $usersQuery->get();
        $fcmTokens = [];

        foreach ($users as $user) {
            $conv = Conversation::whereNull('trip_id')
                ->whereNull('booking_id')
                ->whereHas('participants', fn ($q) => $q->where('users.id', $adminId))
                ->whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
                ->first();

            if (! $conv) {
                $conv = Conversation::create(['type' => 'support', 'trip_id' => null, 'booking_id' => null]);
                $conv->participants()->attach([$adminId, $user->id]);
            }

            Message::create([
                'conversation_id' => $conv->id,
                'sender_id'       => $adminId,
                'body'            => trim($this->broadcastMessage),
            ]);

            $conv->touch();
            if ($user->fcm_token) $fcmTokens[] = $user->fcm_token;
        }

        if (! empty($fcmTokens)) {
            app(FcmService::class)->sendToMultiple(
                $fcmTokens, 'Minizon Admin', trim($this->broadcastMessage), ['type' => 'admin_broadcast']
            );
        }

        $this->flash          = "Message diffusé à {$users->count()} utilisateur(s) avec succès.";
        $this->flashType      = 'success';
        $this->broadcastMessage = '';
        $this->showBroadcast  = false;
    }

    public function clearFlash(): void { $this->flash = null; }

    // ─────────────────────────────────────────────────────────
    //  Render
    // ─────────────────────────────────────────────────────────

    public function render()
    {
        $adminId = $this->getAdminUserId() ?? 0;

        $query = Conversation::with(['participants.profile', 'participants.role', 'lastMessage'])
            ->whereNull('trip_id')
            ->whereNull('booking_id')
            ->whereHas('participants', fn ($q) => $q->where('users.id', $adminId))
            ->whereHas('participants', fn ($q) => $q->where('users.id', '!=', $adminId));

        if ($this->search !== '') {
            $s = '%' . $this->search . '%';
            $query->whereHas('participants', fn ($q) => $q
                ->where('users.id', '!=', $adminId)
                ->where(fn ($qb) => $qb
                    ->where('phone', 'like', $s)
                    ->orWhereHas('profile', fn ($p) => $p->where('first_name', 'like', $s)->orWhere('last_name', 'like', $s))
                )
            );
        }

        if ($this->roleFilter !== '') {
            $query->whereHas('participants', fn ($q) => $q
                ->where('users.id', '!=', $adminId)
                ->whereHas('role', fn ($r) => $r->where('name', $this->roleFilter))
            );
        }

        $conversations = $query->withCount([
            'messages as unread_count' => fn ($q) => $q->where('sender_id', '!=', $adminId)->whereNull('read_at'),
        ])->orderByDesc('updated_at')->get();

        $totalUnread = $conversations->sum('unread_count');

        $selectedConv = null;
        $convMessages = collect();

        if ($this->selectedId) {
            $selectedConv = Conversation::with(['participants.profile', 'participants.role'])->find($this->selectedId);
            $convMessages = Message::where('conversation_id', $this->selectedId)
                ->with('sender.profile')
                ->orderBy('created_at')
                ->get();
        }

        $composeUsers = collect();
        if ($this->showCompose && strlen($this->composeSearch) >= 2) {
            $s = '%' . $this->composeSearch . '%';
            $composeUsers = User::with(['profile', 'role'])
                ->where('is_blocked', false)
                ->where('id', '!=', $adminId)
                ->whereHas('role', $this->composeRole !== ''
                    ? fn ($q) => $q->where('name', $this->composeRole)
                    : fn ($q) => $q->whereIn('name', ['driver', 'passenger'])
                )
                ->where(fn ($qb) => $qb
                    ->where('phone', 'like', $s)
                    ->orWhereHas('profile', fn ($p) => $p->where('first_name', 'like', $s)->orWhere('last_name', 'like', $s))
                )
                ->limit(10)
                ->get();
        }

        $stats = [
            'conversations' => $conversations->count(),
            'unread'        => $totalUnread,
            'today'         => $conversations->filter(fn ($c) => $c->created_at->isToday())->count(),
        ];

        return view('admin.communication', [
            'conversations' => $conversations,
            'totalUnread'   => $totalUnread,
            'selectedConv'  => $selectedConv,
            'convMessages'  => $convMessages,
            'composeUsers'  => $composeUsers,
            'stats'         => $stats,
            'adminId'       => $adminId,
        ])->layout('admin.layouts.app', ['title' => 'Messagerie']);
    }

    // ─────────────────────────────────────────────────────────
    //  Helper : ID de l'utilisateur admin dans la table users
    // ─────────────────────────────────────────────────────────

    private function getAdminUserId(): ?int
    {
        return User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->value('id');
    }
}
