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

    // ── Filtres liste (ancien) ────────────────────────────
    public string $search     = '';
    public string $typeFilter = '';
    public ?int   $selectedId = null;

    // ── Panneau d'envoi admin ─────────────────────────────
    public bool   $showChat       = false;
    public string $chatSearch     = '';
    public string $chatRole       = '';
    public ?int   $chatConvId     = null;   // conversation sélectionnée dans le panneau
    public string $chatMessage    = '';
    public $chatAttachment        = null;

    // ── Diffusion ─────────────────────────────────────────
    public bool   $showBroadcast    = false;
    public string $broadcastMessage = '';
    public string $broadcastTarget  = 'tous';

    // ── Flash ─────────────────────────────────────────────
    public ?string $flash     = null;
    public ?string $flashType = null;

    public function updatingSearch(): void     { $this->resetPage(); }
    public function updatingTypeFilter(): void { $this->resetPage(); }

    public function resetPage(): void { /* pagination si ajoutée plus tard */ }

    // ─── Supervision (ancien) ──────────────────────────────
    public function view(int $id): void   { $this->selectedId = $id; }
    public function closeView(): void     { $this->selectedId = null; }

    // ─── Panneau chat admin ────────────────────────────────
    public function openChat(): void
    {
        $this->showChat    = true;
        $this->chatConvId  = null;
        $this->chatMessage = '';
        $this->chatAttachment = null;
    }

    public function closeChat(): void   { $this->showChat = false; }

    public function startChatWith(string $userUuid): void
    {
        $adminId    = $this->getAdminUserId();
        if (! $adminId) return;

        $target = User::where('uuid', $userUuid)->first();
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

        $this->chatConvId  = $conv->id;
        $this->chatMessage = '';
        $this->chatAttachment = null;
        $this->dispatch('chat-panel-opened');
    }

    public function sendChatMessage(): void
    {
        $this->validate([
            'chatMessage'    => 'nullable|string|max:2000',
            'chatAttachment' => 'nullable|file|max:25600|mimes:jpeg,png,webp,gif,pdf,doc,docx,mp3,m4a,aac,ogg,opus,wav,amr,webm,mp4',
        ]);

        $hasText = trim($this->chatMessage) !== '';
        $hasFile = $this->chatAttachment !== null;
        if (! $hasText && ! $hasFile) return;

        $adminId = $this->getAdminUserId();
        if (! $adminId) {
            $this->flash     = "Aucun utilisateur avec le rôle 'admin' trouvé dans la table users.";
            $this->flashType = 'error';
            return;
        }

        $conv = Conversation::with('participants')->findOrFail($this->chatConvId);

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
            'audio' => '🎙️ Message vocal', 'image' => '📷 Photo', default => '📄 Document',
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

        $adminId = $this->getAdminUserId();
        if (! $adminId) return;

        $q = User::where('is_blocked', false)->where('id', '!=', $adminId);

        match ($this->broadcastTarget) {
            'tous_conducteurs' => $q->whereHas('role', fn ($r) => $r->where('name', 'driver')),
            'tous_passagers'   => $q->whereHas('role', fn ($r) => $r->where('name', 'passenger')),
            'en_ligne'         => $q->whereHas('role', fn ($r) => $r->where('name', 'driver'))->where('is_online', true),
            'en_trajet'        => $q->whereHas('role', fn ($r) => $r->where('name', 'driver'))
                                    ->whereHas('trips', fn ($t) => $t->where('status', 'active')),
            default            => $q->whereHas('role', fn ($r) => $r->whereIn('name', ['driver', 'passenger'])),
        };

        $users = $q->get();
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
            app(FcmService::class)->sendToMultiple($tokens, 'Minizon Admin', trim($this->broadcastMessage), ['type' => 'admin_broadcast']);
        }

        $this->flash          = "Message diffusé à {$users->count()} utilisateur(s).";
        $this->flashType      = 'success';
        $this->broadcastMessage = '';
        $this->showBroadcast  = false;
    }

    public function clearFlash(): void { $this->flash = null; }

    // ─── Render ─────────────────────────────────────────────
    public function render()
    {
        $adminId = $this->getAdminUserId() ?? 0;

        // ── Ancien : supervision de toutes les conversations ──
        $query = Conversation::with([
            'participants.profile',
            'trip',
            'messages' => fn ($q) => $q->latest()->limit(1),
        ])
        ->when($this->search, fn ($q) => $q->whereHas('participants', function ($q2) {
            $s = '%' . $this->search . '%';
            $q2->where('phone', 'like', $s)
               ->orWhereHas('profile', fn ($p) => $p->where('first_name', 'like', $s)
                   ->orWhere('last_name', 'like', $s));
        }))
        ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
        ->withCount('messages')
        ->orderByDesc('updated_at');

        $stats = [
            'total'   => Conversation::count(),
            'messages'=> Message::count(),
            'today'   => Conversation::whereDate('created_at', today())->count(),
            'flagged' => Message::whereNotNull('deleted_at')->withTrashed()->count(),
        ];

        $selectedConv = $this->selectedId
            ? Conversation::with(['participants.profile', 'trip', 'messages.sender.profile'])->find($this->selectedId)
            : null;

        // ── Panneau chat : recherche utilisateur ──
        $chatUsers = collect();
        if ($this->showChat && ! $this->chatConvId && strlen($this->chatSearch) >= 2) {
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

        // ── Panneau chat : messages de la conversation ──
        $chatConv = null;
        $chatMessages = collect();
        if ($this->chatConvId) {
            $chatConv     = Conversation::with(['participants.profile'])->find($this->chatConvId);
            $chatMessages = Message::where('conversation_id', $this->chatConvId)
                ->orderBy('created_at')
                ->get();
        }

        return view('admin.communication', [
            'conversations' => $query->paginate(20),
            'stats'         => $stats,
            'selectedConv'  => $selectedConv,
            'adminId'       => $adminId,
            'chatUsers'     => $chatUsers,
            'chatConv'      => $chatConv,
            'chatMessages'  => $chatMessages,
        ])->layout('admin.layouts.app', ['title' => 'Communication']);
    }

    private function getAdminUserId(): ?int
    {
        return User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->value('id');
    }
}
