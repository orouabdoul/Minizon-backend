<div>
<style>
/* ── Layout ────────────────────────────────────────────── */
.msg-page{display:flex;flex-direction:column;height:calc(100vh - 64px);background:#F2F4F7;overflow:hidden}
.msg-top{padding:20px 28px 0;flex-shrink:0}
.msg-top h1{font-size:20px;font-weight:700;color:#111827;margin:0 0 2px}
.msg-top p{font-size:13px;color:#6B7280;margin:0 0 16px}

.msg-stats{display:flex;gap:10px;margin-bottom:14px}
.msg-stat{background:#fff;border-radius:10px;padding:10px 18px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;color:#374151}
.msg-stat .ic{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}

.msg-body{display:flex;flex:1;overflow:hidden;padding:0 28px 20px;gap:16px}

/* ── Colonne gauche (liste) ────────────────────────────── */
.conv-col{width:320px;flex-shrink:0;display:flex;flex-direction:column;gap:8px}
.conv-toolbar{background:#fff;border-radius:12px;padding:10px 12px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.conv-search{flex:1;min-width:0;border:1.5px solid #E5E7EB;border-radius:8px;padding:6px 10px;font-size:12px;outline:none}
.conv-search:focus{border-color:#1A5FB4}
.conv-role-select{border:1.5px solid #E5E7EB;border-radius:8px;padding:6px 8px;font-size:12px;outline:none;background:#fff;cursor:pointer}
.conv-role-select:focus{border-color:#1A5FB4}
.btn-compose{padding:6px 12px;background:#1A5FB4;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;font-family:inherit;transition:background .15s}
.btn-compose:hover{background:#0F4A9E}
.btn-broadcast{padding:6px 12px;background:#7C3AED;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;font-family:inherit;transition:background .15s}
.btn-broadcast:hover{background:#6D28D9}

.conv-list{flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:6px}
.conv-item{background:#fff;border-radius:10px;padding:12px 14px;box-shadow:0 1px 3px rgba(0,0,0,.06);cursor:pointer;border:2px solid transparent;transition:all .15s;display:flex;gap:10px;align-items:center}
.conv-item:hover{border-color:#BFDBFE}
.conv-item.active{border-color:#1A5FB4;background:#F0F7FF}
.conv-av{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;position:relative}
.conv-av .unread-dot{position:absolute;top:-2px;right:-2px;width:10px;height:10px;background:#EF4444;border-radius:50%;border:2px solid #fff}
.conv-info{flex:1;min-width:0}
.conv-name{font-size:13px;font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conv-preview{font-size:11px;color:#6B7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px}
.conv-right{display:flex;flex-direction:column;align-items:flex-end;gap:3px;flex-shrink:0}
.conv-time{font-size:10px;color:#9CA3AF}
.unread-badge{background:#EF4444;color:#fff;border-radius:10px;padding:1px 6px;font-size:10px;font-weight:700}
.role-pill{font-size:9px;padding:1px 6px;border-radius:8px;font-weight:600}
.pill-driver{background:#D1FAE5;color:#065F46}
.pill-passenger{background:#EFF6FF;color:#1A5FB4}

/* ── Colonne droite (chat) ──────────────────────────────── */
.chat-col{flex:1;min-width:0;display:flex;flex-direction:column;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
.chat-empty{flex:1;background:#fff;border-radius:14px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#9CA3AF;gap:10px}
.chat-empty .icon{font-size:48px}

.chat-head{background:#1A5FB4;color:#fff;padding:14px 20px;display:flex;align-items:center;gap:12px;flex-shrink:0}
.chat-head-av{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#1A5FB4;background:#fff;flex-shrink:0}
.chat-head-info{flex:1}
.chat-head-name{font-size:14px;font-weight:700}
.chat-head-sub{font-size:11px;opacity:.75;margin-top:1px}
.chat-head-close{width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,.15);border:none;color:#fff;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center}
.chat-head-close:hover{background:rgba(255,255,255,.25)}

.chat-messages{flex:1;overflow-y:auto;padding:16px;background:#F8FAFC;display:flex;flex-direction:column;gap:12px}

.msg-row{display:flex;flex-direction:column;gap:3px;max-width:72%}
.msg-row.me{align-self:flex-end;align-items:flex-end}
.msg-row.them{align-self:flex-start}
.msg-bubble{padding:10px 14px;border-radius:16px;font-size:13px;line-height:1.5;word-break:break-word}
.msg-row.me .msg-bubble{background:#1A5FB4;color:#fff;border-bottom-right-radius:4px}
.msg-row.them .msg-bubble{background:#fff;color:#374151;border-bottom-left-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.msg-meta{font-size:10px;color:#9CA3AF;display:flex;align-items:center;gap:5px}
.read-tick{color:#60A5FA;font-size:11px}

.msg-attachment{margin-top:6px}
.msg-attachment img{max-width:220px;border-radius:10px;cursor:pointer}
.msg-audio{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.15);padding:8px 12px;border-radius:10px;min-width:180px}
.msg-audio audio{height:30px;flex:1}
.msg-doc{display:flex;align-items:center;gap:8px;padding:8px 12px;background:rgba(255,255,255,.12);border-radius:10px;text-decoration:none;color:inherit;font-size:12px}

/* ── Zone de saisie ─────────────────────────────────────── */
.chat-input-area{background:#fff;padding:12px 16px;border-top:1px solid #F3F4F6;flex-shrink:0}
.attachment-preview{display:flex;align-items:center;gap:8px;background:#EFF6FF;border-radius:8px;padding:6px 10px;margin-bottom:8px;font-size:12px;color:#1A5FB4}
.attachment-preview button{background:none;border:none;color:#EF4444;cursor:pointer;font-size:14px;padding:0;margin-left:auto}
.input-row{display:flex;align-items:flex-end;gap:8px}
.msg-textarea{flex:1;border:1.5px solid #E5E7EB;border-radius:12px;padding:10px 14px;font-size:13px;resize:none;outline:none;min-height:40px;max-height:120px;font-family:inherit;line-height:1.5}
.msg-textarea:focus{border-color:#1A5FB4}
.input-btn{width:38px;height:38px;border-radius:10px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;transition:all .15s}
.input-btn:hover{border-color:#1A5FB4;background:#EFF6FF}
.input-btn.recording{border-color:#EF4444;background:#FEE2E2;animation:pulse-btn 1s infinite}
.send-btn{width:38px;height:38px;border-radius:10px;border:none;background:#1A5FB4;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;transition:background .15s}
.send-btn:hover{background:#0F4A9E}
.send-btn:disabled{background:#9CA3AF;cursor:not-allowed}
.rec-indicator{display:none;align-items:center;gap:6px;font-size:11px;color:#EF4444;font-weight:600;margin-bottom:6px}
.rec-dot{width:8px;height:8px;border-radius:50%;background:#EF4444;animation:pulse-btn 1s infinite}
@keyframes pulse-btn{0%,100%{opacity:1}50%{opacity:.4}}

/* ── Modals ────────────────────────────────────────────── */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(2px)}
.modal-box{background:#fff;border-radius:16px;padding:28px;width:480px;max-width:95vw;box-shadow:0 8px 30px rgba(0,0,0,.15)}
.modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.modal-head h2{font-size:16px;font-weight:700;color:#111827;margin:0}
.modal-close{width:30px;height:30px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;font-size:16px;color:#6B7280;display:flex;align-items:center;justify-content:center}
.modal-close:hover{background:#FEE2E2;border-color:#EF4444;color:#EF4444}
.form-group{margin-bottom:14px}
.form-label{font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px}
.form-input{width:100%;padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;font-family:inherit;box-sizing:border-box}
.form-input:focus{border-color:#1A5FB4}
.form-select{width:100%;padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;font-family:inherit;background:#fff;cursor:pointer;box-sizing:border-box}
.form-select:focus{border-color:#1A5FB4}
.form-textarea{width:100%;padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;font-family:inherit;resize:vertical;min-height:90px;box-sizing:border-box}
.form-textarea:focus{border-color:#1A5FB4}
.user-result{padding:10px 12px;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:background .12s}
.user-result:hover{background:#F3F4F6}
.user-result-av{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0}
.user-result-name{font-size:13px;font-weight:600;color:#111827}
.user-result-sub{font-size:11px;color:#6B7280}
.btn-full{width:100%;padding:10px;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-primary-full{background:#1A5FB4;color:#fff}
.btn-primary-full:hover{background:#0F4A9E}
.btn-purple-full{background:#7C3AED;color:#fff}
.btn-purple-full:hover{background:#6D28D9}
.target-options{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:4px}
.target-option{padding:10px;border:1.5px solid #E5E7EB;border-radius:8px;cursor:pointer;text-align:center;font-size:12px;font-weight:600;color:#374151;transition:all .15s}
.target-option:hover{border-color:#7C3AED;color:#7C3AED}
.target-option.selected{border-color:#7C3AED;background:#EDE9FE;color:#7C3AED}

/* ── Flash ──────────────────────────────────────────────── */
.flash-bar{padding:12px 16px;border-radius:10px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;margin-bottom:12px}
.flash-success{background:#D1FAE5;color:#065F46;border:1.5px solid #6EE7B7}
.flash-error{background:#FEE2E2;color:#991B1B;border:1.5px solid #FCA5A5}
.flash-close{margin-left:auto;background:none;border:none;cursor:pointer;font-size:16px;color:inherit;padding:0}
</style>

<div class="msg-page">

    {{-- Header ─────────────────────────────────────────── --}}
    <div class="msg-top">
        <h1>💬 Messagerie</h1>
        <p>Envoyez des messages directs, fichiers et audios aux conducteurs et passagers</p>

        {{-- Flash --}}
        @if($flash)
        <div class="flash-bar {{ $flashType === 'success' ? 'flash-success' : 'flash-error' }}">
            {{ $flashType === 'success' ? '✅' : '⚠️' }} {{ $flash }}
            <button class="flash-close" wire:click="clearFlash">✕</button>
        </div>
        @endif

        <div class="msg-stats">
            <div class="msg-stat">
                <div class="ic" style="background:#EFF6FF">💬</div>
                {{ number_format($stats['conversations']) }} conv.
            </div>
            <div class="msg-stat">
                <div class="ic" style="background:#FEE2E2">🔴</div>
                <span style="color:#EF4444">{{ number_format($totalUnread) }}</span> non lus
            </div>
            <div class="msg-stat">
                <div class="ic" style="background:#D1FAE5">📅</div>
                {{ number_format($stats['today']) }} aujourd'hui
            </div>
        </div>
    </div>

    {{-- Corps ──────────────────────────────────────────── --}}
    <div class="msg-body">

        {{-- Colonne gauche —————————————————————————————— --}}
        <div class="conv-col">
            <div class="conv-toolbar">
                <input type="text" class="conv-search" placeholder="🔍 Rechercher…"
                       wire:model.live.debounce.300ms="search">
                <select class="conv-role-select" wire:model.live="roleFilter">
                    <option value="">Tous</option>
                    <option value="driver">Conducteurs</option>
                    <option value="passenger">Passagers</option>
                </select>
                <button class="btn-compose" wire:click="$set('showCompose',true)">✉️ Nouveau</button>
                <button class="btn-broadcast" wire:click="$set('showBroadcast',true)">📢 Diffuser</button>
            </div>

            <div class="conv-list">
                @forelse($conversations as $conv)
                @php
                    $other = $conv->participants->first(fn($p) => $p->id !== $adminId);
                    $pName = trim(($other?->profile?->first_name??'').(' '.($other?->profile?->last_name??''))) ?: ($other?->phone??'Utilisateur');
                    $pInit = strtoupper(substr($other?->profile?->first_name??'U',0,1).substr($other?->profile?->last_name??'',0,1));
                    $role  = $other?->role?->name ?? 'passenger';
                    $pBg   = $role === 'driver' ? '#10B981' : '#1A5FB4';
                    $last  = $conv->lastMessage;
                    $lastText = $last ? ($last->body ?? match($last->attachment_type) {
                        'audio' => '🎙️ Message vocal', 'image' => '📷 Photo', default => '📄 Document'
                    }) : 'Aucun message';
                @endphp
                <div class="conv-item {{ $selectedId === $conv->id ? 'active' : '' }}"
                     wire:click="view({{ $conv->id }})">
                    <div class="conv-av" style="background:{{ $pBg }}">
                        {{ $pInit }}
                        @if($conv->unread_count > 0)<div class="unread-dot"></div>@endif
                    </div>
                    <div class="conv-info">
                        <div style="display:flex;align-items:center;gap:6px">
                            <span class="conv-name">{{ $pName }}</span>
                            <span class="role-pill {{ $role === 'driver' ? 'pill-driver' : 'pill-passenger' }}">
                                {{ $role === 'driver' ? 'Conducteur' : 'Passager' }}
                            </span>
                        </div>
                        <div class="conv-preview">{{ \Illuminate\Support\Str::limit($lastText, 45) }}</div>
                    </div>
                    <div class="conv-right">
                        <span class="conv-time">
                            @if($last) {{ $last->created_at->diffForHumans(short: true) }} @endif
                        </span>
                        @if($conv->unread_count > 0)
                        <span class="unread-badge">{{ $conv->unread_count }}</span>
                        @endif
                    </div>
                </div>
                @empty
                <div style="text-align:center;padding:40px;color:#9CA3AF;font-size:13px">
                    <div style="font-size:32px;margin-bottom:8px">💬</div>
                    Aucune conversation.<br>Cliquez "Nouveau" pour démarrer.
                </div>
                @endforelse
            </div>
        </div>

        {{-- Colonne droite ——————————————————————————————— --}}
        <div class="chat-col">

            @if(! $selectedConv)
            <div class="chat-empty">
                <div class="icon">💬</div>
                <div style="font-size:15px;font-weight:600;color:#374151">Sélectionnez une conversation</div>
                <div style="font-size:12px">ou créez-en une nouvelle</div>
                <button class="btn-compose" style="margin-top:12px" wire:click="$set('showCompose',true)">✉️ Nouveau message</button>
            </div>

            @else
            @php
                $other2  = $selectedConv->participants->first(fn($p) => $p->id !== $adminId);
                $pName2  = trim(($other2?->profile?->first_name??'').(' '.($other2?->profile?->last_name??''))) ?: ($other2?->phone??'Utilisateur');
                $pInit2  = strtoupper(substr($other2?->profile?->first_name??'U',0,1).substr($other2?->profile?->last_name??'',0,1));
                $role2   = $other2?->role?->name ?? 'passenger';
            @endphp

            {{-- En-tête conversation --}}
            <div class="chat-head">
                <div class="chat-head-av">{{ $pInit2 }}</div>
                <div class="chat-head-info">
                    <div class="chat-head-name">{{ $pName2 }}</div>
                    <div class="chat-head-sub">
                        {{ $role2 === 'driver' ? '🚗 Conducteur' : '👤 Passager' }}
                        @if($other2?->phone) · {{ $other2->phone }} @endif
                    </div>
                </div>
                <button class="chat-head-close" wire:click="closeView">✕</button>
            </div>

            {{-- Messages --}}
            <div class="chat-messages" id="chat-messages-box">
                @forelse($convMessages as $msg)
                @php
                    $isMe  = $msg->sender_id === $adminId;
                    $mName = $isMe ? 'Vous' : (trim(($msg->sender?->profile?->first_name??'').(' '.($msg->sender?->profile?->last_name??''))) ?: 'Utilisateur');
                @endphp
                <div class="msg-row {{ $isMe ? 'me' : 'them' }}">
                    @if($msg->body)
                    <div class="msg-bubble">{{ $msg->body }}</div>
                    @endif

                    @if($msg->attachment_path)
                    <div class="msg-attachment msg-bubble {{ $isMe ? '' : '' }}" style="{{ $isMe ? 'background:#1A5FB4' : 'background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08)' }}">
                        @if($msg->attachment_type === 'image')
                        <img src="{{ Storage::disk('public')->url($msg->attachment_path) }}" alt="image">
                        @elseif($msg->attachment_type === 'audio')
                        <div class="msg-audio">
                            <span style="font-size:16px">🎙️</span>
                            <audio controls src="{{ Storage::disk('public')->url($msg->attachment_path) }}"></audio>
                        </div>
                        @else
                        <a class="msg-doc" href="{{ Storage::disk('public')->url($msg->attachment_path) }}" target="_blank" style="{{ $isMe ? 'color:#fff' : 'color:#374151' }}">
                            📄 {{ basename($msg->attachment_path) }}
                        </a>
                        @endif
                    </div>
                    @endif

                    <div class="msg-meta">
                        <span>{{ $msg->created_at->format('H:i') }}</span>
                        @if($isMe && $msg->read_at)<span class="read-tick" title="Lu">✓✓</span>@elseif($isMe)<span style="color:#9CA3AF;font-size:11px">✓</span>@endif
                    </div>
                </div>
                @empty
                <div style="text-align:center;color:#9CA3AF;font-size:13px;padding:40px">
                    Aucun message. Dites bonjour 👋
                </div>
                @endforelse
            </div>

            {{-- Zone de saisie --}}
            <div class="chat-input-area">

                {{-- Prévisualisation pièce jointe --}}
                @if($attachment)
                <div class="attachment-preview">
                    📎 {{ $attachment->getClientOriginalName() }}
                    <button wire:click="$set('attachment', null)">✕</button>
                </div>
                @endif

                {{-- Indicateur enregistrement audio --}}
                <div class="rec-indicator" id="rec-indicator">
                    <div class="rec-dot"></div> Enregistrement en cours…
                </div>

                <div class="input-row">
                    {{-- Fichier --}}
                    <label class="input-btn" title="Joindre un fichier" style="cursor:pointer">
                        📎
                        <input type="file" wire:model="attachment" style="display:none"
                               accept="image/*,application/pdf,.doc,.docx,audio/*">
                    </label>

                    {{-- Micro --}}
                    <button class="input-btn" id="mic-btn" title="Enregistrer un audio" type="button">🎤</button>

                    {{-- Texte --}}
                    <textarea class="msg-textarea" wire:model="newMessage"
                              placeholder="Écrire un message…"
                              rows="1"
                              id="msg-textarea"
                              onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();$wire.sendMessage()}"
                              oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px'"></textarea>

                    {{-- Envoyer --}}
                    <button class="send-btn" wire:click="sendMessage"
                            wire:loading.attr="disabled"
                            wire:target="sendMessage">
                        <span wire:loading.remove wire:target="sendMessage">➤</span>
                        <span wire:loading wire:target="sendMessage" style="font-size:11px">…</span>
                    </button>
                </div>
            </div>

            @endif
        </div>

    </div>{{-- /msg-body --}}
</div>{{-- /msg-page --}}

{{-- Modal : Nouveau message ─────────────────────────────── --}}
@if($showCompose)
<div class="modal-overlay" wire:click.self="$set('showCompose',false)">
    <div class="modal-box">
        <div class="modal-head">
            <h2>✉️ Nouveau message</h2>
            <button class="modal-close" wire:click="$set('showCompose',false)">✕</button>
        </div>

        <div class="form-group">
            <label class="form-label">Type d'utilisateur</label>
            <select class="form-select" wire:model.live="composeRole">
                <option value="">Tous (conducteurs + passagers)</option>
                <option value="driver">Conducteurs uniquement</option>
                <option value="passenger">Passagers uniquement</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Rechercher (nom ou téléphone)</label>
            <input type="text" class="form-input"
                   wire:model.live.debounce.300ms="composeSearch"
                   placeholder="Tapez au moins 2 caractères…">
        </div>

        @if($composeUsers->isNotEmpty())
        <div style="display:flex;flex-direction:column;gap:4px;max-height:220px;overflow-y:auto;border:1.5px solid #E5E7EB;border-radius:8px;padding:6px">
            @foreach($composeUsers as $u)
            @php
                $uName = trim(($u->profile?->first_name??'').(' '.($u->profile?->last_name??''))) ?: $u->phone;
                $uInit = strtoupper(substr($u->profile?->first_name??'U',0,1).substr($u->profile?->last_name??'',0,1));
                $uRole = $u->role?->name ?? 'passenger';
                $uBg   = $uRole === 'driver' ? '#10B981' : '#1A5FB4';
            @endphp
            <div class="user-result" wire:click="startConversation('{{ $u->uuid }}')">
                <div class="user-result-av" style="background:{{ $uBg }}">{{ $uInit }}</div>
                <div>
                    <div class="user-result-name">{{ $uName }}</div>
                    <div class="user-result-sub">{{ $u->phone }} · {{ $uRole === 'driver' ? 'Conducteur' : 'Passager' }}</div>
                </div>
                <span style="margin-left:auto;font-size:11px;color:#1A5FB4;font-weight:600">Ouvrir →</span>
            </div>
            @endforeach
        </div>
        @elseif(strlen($composeSearch) >= 2)
        <div style="text-align:center;padding:20px;color:#9CA3AF;font-size:13px">Aucun résultat trouvé.</div>
        @endif
    </div>
</div>
@endif

{{-- Modal : Diffusion ──────────────────────────────────────── --}}
@if($showBroadcast)
<div class="modal-overlay" wire:click.self="$set('showBroadcast',false)">
    <div class="modal-box">
        <div class="modal-head">
            <h2>📢 Diffusion</h2>
            <button class="modal-close" wire:click="$set('showBroadcast',false)">✕</button>
        </div>

        <div class="form-group">
            <label class="form-label">Destinataires</label>
            <div class="target-options">
                @foreach([
                    ['tous',              '🌍 Tous'],
                    ['tous_conducteurs',  '🚗 Conducteurs'],
                    ['tous_passagers',    '👤 Passagers'],
                    ['en_ligne',          '🟢 En ligne'],
                    ['en_trajet',         '📍 En trajet'],
                ] as [$val, $lbl])
                <div class="target-option {{ $broadcastTarget === $val ? 'selected' : '' }}"
                     wire:click="$set('broadcastTarget','{{ $val }}')">{{ $lbl }}</div>
                @endforeach
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Message</label>
            <textarea class="form-textarea" wire:model="broadcastMessage"
                      placeholder="Votre message à diffuser…"></textarea>
        </div>

        <button class="btn-full btn-purple-full" wire:click="sendBroadcast"
                wire:loading.attr="disabled" wire:target="sendBroadcast">
            <span wire:loading.remove wire:target="sendBroadcast">📢 Envoyer la diffusion</span>
            <span wire:loading wire:target="sendBroadcast">Envoi en cours…</span>
        </button>
    </div>
</div>
@endif

@script
<script>
// ── Scroll to bottom quand la conversation s'ouvre ou un message est envoyé ──
$wire.on('chat-opened', () => {
    requestAnimationFrame(() => {
        const box = document.getElementById('chat-messages-box');
        if (box) box.scrollTop = box.scrollHeight;
    });
});

// ── Enregistrement audio avec MediaRecorder ──────────────────────────────────
let recorder   = null;
let chunks     = [];
let audioStream = null;
let isRecording = false;

const micBtn = document.getElementById('mic-btn');
const recInd = document.getElementById('rec-indicator');

if (micBtn) {
    micBtn.addEventListener('click', async () => {
        if (!isRecording) {
            try {
                audioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                recorder    = new MediaRecorder(audioStream, { mimeType: 'audio/webm' });
                chunks      = [];

                recorder.ondataavailable = (e) => { if (e.data.size > 0) chunks.push(e.data); };

                recorder.onstop = () => {
                    const blob = new Blob(chunks, { type: 'audio/webm' });
                    const file = new File([blob], `voice-${Date.now()}.webm`, { type: 'audio/webm' });
                    $wire.upload('attachment', file,
                        () => { /* done */ },
                        () => { alert('Erreur upload audio'); },
                        () => { /* progress */ }
                    );
                    audioStream.getTracks().forEach(t => t.stop());
                };

                recorder.start();
                isRecording = true;
                micBtn.classList.add('recording');
                micBtn.textContent = '⏹';
                if (recInd) recInd.style.display = 'flex';

            } catch (e) {
                alert('Microphone inaccessible : ' + e.message);
            }
        } else {
            recorder.stop();
            isRecording = false;
            micBtn.classList.remove('recording');
            micBtn.textContent = '🎤';
            if (recInd) recInd.style.display = 'none';
        }
    });
}
</script>
@endscript

</div>
