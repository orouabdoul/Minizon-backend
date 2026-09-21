<?php

namespace App\Livewire\Admin;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\FcmService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Communication extends Component
{
    use WithPagination, WithFileUploads;

    // ── Filtres supervision (ancien) ──────────────────────
    public string $search     = '';
    public string $typeFilter = '';
    public ?int   $selectedId = null;

    // ── Panneau chat admin ────────────────────────────────
    public bool    $showChat         = false;
    public string  $chatSearch       = '';
    public string  $chatRole         = '';
    public ?string $chatTargetUuid   = null;  // UUID de l'utilisateur sélectionné
    public ?int    $chatConvId       = null;  // ID conversation (null = pas encore créée)
    public string  $chatMessage      = '';
    public $chatAttachment           = null;

    // ── Diffusion ─────────────────────────────────────────
    public bool   $showBroadcast    = false;
    public string $broadcastMessage = '';
    public string $broadcastTarget  = 'tous';

    // ── Flash ─────────────────────────────────────────────
    public ?string $flash     = null;
    public ?string $flashType = null;

    public function updatingSearch(): void     { $this->resetPage(); }
    public function updatingTypeFilter(): void { $this->resetPage(); }

    // ─── Supervision (ancien) ──────────────────────────────

    public function view(int $id): void   { $this->selectedId = $id; }
    public function closeView(): void     { $this->selectedId = null; }

    // ─── Panneau chat ──────────────────────────────────────

    public function openChat(): void
    {
        $this->showChat       = true;
        $this->chatTargetUuid = null;
        $this->chatConvId     = null;
        $this->chatMessage    = '';
        $this->chatAttachment = null;
        $this->chatSearch     = '';
    }

    public function closeChat(): void
    {
        $this->showChat       = false;
        $this->chatTargetUuid = null;
        $this->chatConvId     = null;
    }

    public function backToSearch(): void
    {
        $this->chatTargetUuid = null;
        $this->chatConvId     = null;
        $this->chatMessage    = '';
        $this->chatAttachment = null;
    }

    /**
     * Sélectionner un utilisateur → ouvre la vue chat immédiatement,
     * sans attendre l'existence d'un admin user.
     */
    public function startChatWith(string $userUuid): void
    {
        $this->chatTargetUuid = $userUuid;
        $this->chatConvId     = null;
        $this->chatMessage    = '';
        $this->chatAttachment = null;

        // Cherche une conversation existante (si un admin user existe)
        $adminId = $this->resolveAdminUserId();
        if ($adminId) {
            $target = User::where('uuid', $userUuid)->value('id');
            if ($target) {
                $conv = Conversation::whereNull('trip_id')
                    ->whereNull('booking_id')
                    ->whereHas('participants', fn ($q) => $q->where('users.id', $adminId))
                    ->whereHas('participants', fn ($q) => $q->where('users.id', $target))
                    ->first();
                $this->chatConvId = $conv?->id;
            }
        }

        $this->dispatch('chat-panel-opened');
    }

    /**
     * Envoyer un message. Crée la conversation si elle n'existe pas encore.
     */
    public function sendChatMessage(): void
    {
        $this->validate([
            'chatMessage'    => 'nullable|string|max:2000',
            'chatAttachment' => 'nullable|file|max:25600|mimes:jpeg,png,webp,gif,pdf,doc,docx,mp3,m4a,aac,ogg,opus,wav,amr,webm,mp4',
        ]);

        $hasText = trim($this->chatMessage) !== '';
        $hasFile = $this->chatAttachment !== null;
        if (! $hasText && ! $hasFile) return;

        if (! $this->chatTargetUuid) return;

        $adminId = $this->resolveAdminUserId();
        if (! $adminId) {
            $this->flash     = "Impossible d'envoyer : aucun utilisateur avec le rôle 'admin' trouvé. Créez-en un via la page Utilisateurs ou lancez : php artisan db:seed --class=AdminUserSeeder";
            $this->flashType = 'error';
            return;
        }

        // Trouver ou créer la conversation
        if (! $this->chatConvId) {
            $target = User::where('uuid', $this->chatTargetUuid)->first();
            if (! $target) return;

            $conv = Conversation::whereNull('trip_id')
                ->whereNull('booking_id')
                ->whereHas('participants', fn ($q) => $q->where('users.id', $adminId))
                ->whereHas('participants', fn ($q) => $q->where('users.id', $target->id))
                ->first();

            if (! $conv) {
                $conv = Conversation::create(['type' => 'support', 'trip_id' => null, 'booking_id' => null]);
                $conv->participants()->attach([$adminId, $target->id]);
            }

            $this->chatConvId = $conv->id;
        }

        $conv = Conversation::with('participants')->find($this->chatConvId);
        if (! $conv) return;

        $attachmentPath = null;
        $attachmentType = null;

        if ($hasFile) {
            $ext  = strtolower($this->chatAttachment->getClientOriginalExtension());
            $mime = strtolower($this->chatAttachment->getMimeType() ?? '');
            $attachmentType = match (true) {
                str_starts_with($mime, 'image/')                                                      => 'image',
                str_starts_with($mime, 'audio/')                                                      => 'audio',
                in_array($ext, ['mp3', 'm4a', 'aac', 'ogg', 'opus', 'wav', 'amr', 'webm', 'flac']) => 'audio',
                in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])                                 => 'image',
                default                                                                               => 'document',
            };
            $filename       = Str::uuid() . '.' . $ext;
            $attachmentPath = $this->chatAttachment->storeAs('chat/' . $conv->uuid, $filename, 'public');
        }

        Message::create([
            'conversation_id' => $conv->id,
            'sender_id'       => $adminId,
            'body'            => $hasText ? trim($this->chatMessage) : null,
            'attachment_path' => $attachmentPath,
            'attachment_type' => $attachmentType,
        ]);

        $conv->touch();

        $preview = $hasText ? trim($this->chatMessage) : match ($attachmentType) {
            'audio' => '🎙️ Message vocal',
            'image' => '📷 Photo',
            default => '📄 Document',
        };

        $tokens = $conv->participants
            ->filter(fn ($u) => $u->id !== $adminId)
            ->pluck('fcm_token')->filter()->values()->all();

        if (! empty($tokens)) {
            app(FcmService::class)->sendToMultiple($tokens, 'Minizon Admin', $preview, [
                'type' => 'new_message', 'conversation_uuid' => $conv->uuid,
            ]);
        }

        $this->chatMessage    = '';
        $this->chatAttachment = null;
        $this->dispatch('chat-panel-opened');
    }

    // ─── Diffusion ─────────────────────────────────────────

    public function sendBroadcast(): void
    {
        $this->validate([
            'broadcastMessage' => 'required|string|max:2000',
            'broadcastTarget'  => 'required|in:tous,tous_conducteurs,tous_passagers,en_ligne,en_trajet',
        ]);

        $adminId = $this->resolveAdminUserId();
        if (! $adminId) {
            $this->flash     = "Impossible de diffuser : aucun utilisateur avec le rôle 'admin' trouvé. Créez-en un via la page Utilisateurs.";
            $this->flashType = 'error';
            $this->showBroadcast = false;
            return;
        }

        $q = User::where('is_blocked', false)->where('id', '!=', $adminId);

        if ($this->broadcastTarget === 'tous_conducteurs') {
            $q->whereHas('role', fn ($r) => $r->where('name', 'driver'));
        } elseif ($this->broadcastTarget === 'tous_passagers') {
            $q->whereHas('role', fn ($r) => $r->where('name', 'passenger'));
        } elseif ($this->broadcastTarget === 'en_ligne') {
            $q->whereHas('role', fn ($r) => $r->where('name', 'driver'))->where('is_online', true);
        } elseif ($this->broadcastTarget === 'en_trajet') {
            $q->whereHas('role', fn ($r) => $r->where('name', 'driver'))
              ->whereHas('trips', fn ($t) => $t->where('status', 'active'));
        } else {
            $q->whereHas('role', fn ($r) => $r->whereIn('name', ['driver', 'passenger']));
        }

        $users  = $q->get();
        $tokens = [];

        foreach ($users as $user) {
            $conv = Conversation::whereNull('trip_id')->whereNull('booking_id')
                ->whereHas('participants', fn ($p) => $p->where('users.id', $adminId))
                ->whereHas('participants', fn ($p) => $p->where('users.id', $user->id))
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
            if ($user->fcm_token) $tokens[] = $user->fcm_token;
        }

        if (! empty($tokens)) {
            app(FcmService::class)->sendToMultiple(
                $tokens, 'Minizon Admin', trim($this->broadcastMessage), ['type' => 'admin_broadcast']
            );
        }

        $this->flash          = "✅ Message diffusé à {$users->count()} utilisateur(s).";
        $this->flashType      = 'success';
        $this->broadcastMessage = '';
        $this->showBroadcast  = false;
    }

    public function clearFlash(): void { $this->flash = null; }

    // ─── Render ─────────────────────────────────────────────

    public function render()
    {
        $adminId = $this->resolveAdminUserId() ?? 0;

        // Supervision : toutes les conversations
        $query = Conversation::with([
            'participants.profile',
            'trip',
            'lastMessage' => fn ($q) => $q->withoutGlobalScopes()->with('sender.profile'),
        ])
        ->when($this->search, fn ($q) => $q->whereHas('participants', function ($q2) {
            $s = '%' . $this->search . '%';
            $q2->where('phone', 'like', $s)
               ->orWhereHas('profile', fn ($p) => $p->where('first_name', 'like', $s)->orWhere('last_name', 'like', $s));
        }))
        ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
        ->withCount(['messages' => fn ($q) => $q->withoutGlobalScopes()])
        ->orderByDesc('updated_at');

        try {
            $stats = [
                'total'   => Conversation::count(),
                'messages'=> Message::withoutGlobalScopes()->count(),
                'today'   => Conversation::whereDate('created_at', today())->count(),
                'flagged' => Message::onlyTrashed()->count(),
            ];
        } catch (\Throwable) {
            $stats = ['total' => 0, 'messages' => 0, 'today' => 0, 'flagged' => 0];
        }

        $selectedConv = $this->selectedId
            ? Conversation::with([
                'participants.profile',
                'trip',
                'messages' => fn ($q) => $q->withoutGlobalScopes()->with('sender.profile'),
            ])->find($this->selectedId)
            : null;

        // Panneau chat : utilisateur ciblé
        $chatTargetUser = null;
        if ($this->chatTargetUuid) {
            $chatTargetUser = User::with(['profile', 'role'])->where('uuid', $this->chatTargetUuid)->first();
        }

        // Panneau chat : messages
        $chatMessages = collect();
        if ($this->chatConvId) {
            $chatMessages = Message::withoutGlobalScopes()
                ->where('conversation_id', $this->chatConvId)
                ->orderBy('created_at')
                ->get();

            // Marquer les messages du destinataire comme lus
            if ($adminId) {
                Message::withoutGlobalScopes()
                    ->where('conversation_id', $this->chatConvId)
                    ->where('sender_id', '!=', $adminId)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            }
        }

        // Panneau chat : résultats de recherche
        $chatUsers = collect();
        if ($this->showChat && ! $this->chatTargetUuid && strlen($this->chatSearch) >= 2) {
            $s = '%' . $this->chatSearch . '%';
            $chatUsers = User::with(['profile', 'role'])
                ->where('is_blocked', false)
                ->where('id', '!=', $adminId)
                ->whereHas('role', $this->chatRole !== ''
                    ? fn ($q) => $q->where('name', $this->chatRole)
                    : fn ($q) => $q->whereIn('name', ['driver', 'passenger'])
                )
                ->where(fn ($qb) => $qb
                    ->where('phone', 'like', $s)
                    ->orWhereHas('profile', fn ($p) => $p->where('first_name', 'like', $s)->orWhere('last_name', 'like', $s))
                )
                ->limit(10)
                ->get();
        }

        try {
            $conversations = $query->paginate(20);
        } catch (\Throwable) {
            $conversations = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }

        return view('admin.communication', [
            'conversations'  => $conversations,
            'stats'          => $stats,
            'selectedConv'   => $selectedConv,
            'adminId'        => $adminId,
            'chatUsers'      => $chatUsers,
            'chatTargetUser' => $chatTargetUser,
            'chatMessages'   => $chatMessages,
            'hasAdminUser'   => $adminId > 0,
        ])->layout('admin.layouts.app', ['title' => 'Communication']);
    }

    // ─── Helpers ────────────────────────────────────────────

    /**
     * Cherche l'ID de l'utilisateur avec rôle admin dans la table users.
     * Essaie aussi par email (web admin ↔ users table).
     */
    private function resolveAdminUserId(): ?int
    {
        // 1. Par rôle
        $id = User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->value('id');
        if ($id) return $id;

        // 2. Par email (si l'admin web a un compte user avec le même email)
        $email = auth('admin')->user()?->email;
        if ($email) {
            $id = User::where('email', $email)->value('id');
            if ($id) return $id;
        }

        return null;
    }
}
