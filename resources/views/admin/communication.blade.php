<div>
<style>
/* ══ Ancienne supervision ════════════════════════════════ */
.comm-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.comm-header{margin-bottom:24px}
.comm-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.comm-header p{font-size:13px;color:#6B7280;margin:0}

.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;gap:12px}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-value{font-size:22px;font-weight:700;color:#111827}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px}

.filter-bar{background:#fff;border-radius:12px;padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:18px}
.filter-input{flex:1;min-width:200px;padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none}
.filter-input:focus{border-color:#1A5FB4}
.filter-select{padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;background:#fff;cursor:pointer}

.conv-grid{display:flex;flex-direction:column;gap:8px}
.conv-item{background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;gap:14px;cursor:pointer;transition:all .15s;border:2px solid transparent}
.conv-item:hover{border-color:#BFDBFE;box-shadow:0 3px 10px rgba(0,0,0,.08)}
.conv-item.selected{border-color:#1A5FB4}

.avatars{position:relative;width:48px;height:36px;flex-shrink:0}
.av{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;border:2px solid #fff;position:absolute}
.av:first-child{left:0;top:0;z-index:2}
.av:last-child{left:12px;top:4px;z-index:1}

.conv-content{flex:1;min-width:0}
.conv-participants{font-size:13px;font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conv-last{font-size:12px;color:#6B7280;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conv-meta{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0}
.conv-time{font-size:11px;color:#9CA3AF}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}

.empty-state{background:#fff;border-radius:12px;padding:60px;text-align:center;color:#9CA3AF;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.empty-state .icon{font-size:40px;margin-bottom:12px}

/* ── Slide-over supervision (ancien) ─────────────────────── */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:560px;max-width:96vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:18px 22px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;background:#1A5FB4;color:#fff}
.panel-head h2{font-size:15px;font-weight:700;margin:0}
.panel-close{width:30px;height:30px;border-radius:8px;border:none;background:rgba(255,255,255,.2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;color:#fff;transition:background .15s}
.panel-close:hover{background:rgba(255,255,255,.35)}
.panel-sub{font-size:11px;opacity:.85;margin-top:2px}

.msg-list{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#F2F4F7}
.msg-item{max-width:78%;display:flex;flex-direction:column;gap:3px}
.msg-item.mine{align-self:flex-end;align-items:flex-end}
.msg-item.other{align-self:flex-start}
.msg-bubble{padding:10px 14px;border-radius:16px;font-size:13px;line-height:1.5;word-break:break-word}
.msg-item.mine .msg-bubble{background:#1A5FB4;color:#fff;border-bottom-right-radius:4px}
.msg-item.other .msg-bubble{background:#fff;color:#374151;border-bottom-left-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.msg-meta{font-size:10px;color:#9CA3AF;display:flex;align-items:center;gap:6px}
.msg-sender{font-weight:600;color:#374151}

.panel-footer{padding:14px 18px;border-top:1px solid #F3F4F6;background:#fff;flex-shrink:0;text-align:center;font-size:12px;color:#9CA3AF}

/* ══ Bouton flottant + panneau chat admin ══════════════════ */
.fab-chat{position:fixed;bottom:32px;right:32px;z-index:900;width:56px;height:56px;border-radius:50%;background:#1A5FB4;color:#fff;border:none;box-shadow:0 4px 16px rgba(26,95,180,.45);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:24px;transition:all .2s}
.fab-chat:hover{background:#0F4A9E;transform:scale(1.08)}
.fab-badge{position:absolute;top:-4px;right:-4px;background:#EF4444;color:#fff;border-radius:10px;font-size:10px;font-weight:700;padding:1px 5px;border:2px solid #fff}
.fab-broadcast{position:fixed;bottom:100px;right:32px;z-index:900;width:44px;height:44px;border-radius:50%;background:#7C3AED;color:#fff;border:none;box-shadow:0 4px 14px rgba(124,58,237,.4);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;transition:all .2s}
.fab-broadcast:hover{background:#6D28D9;transform:scale(1.08)}

/* Panneau chat admin */
.chat-panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:1100;backdrop-filter:blur(2px)}
.chat-panel{position:fixed;bottom:0;right:0;width:400px;max-width:95vw;height:560px;max-height:90vh;background:#fff;z-index:1101;display:flex;flex-direction:column;border-radius:16px 16px 0 0;box-shadow:-4px -4px 30px rgba(0,0,0,.15)}
.chat-panel-head{background:#1A5FB4;color:#fff;padding:14px 18px;display:flex;align-items:center;gap:10px;border-radius:16px 16px 0 0;flex-shrink:0}
.chat-panel-head h3{font-size:14px;font-weight:700;margin:0;flex:1}
.cp-btn{width:28px;height:28px;border-radius:7px;background:rgba(255,255,255,.15);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px}
.cp-btn:hover{background:rgba(255,255,255,.25)}

.chat-search-area{padding:12px 14px;border-bottom:1px solid #F3F4F6;flex-shrink:0}
.chat-search-row{display:flex;gap:8px;align-items:center}
.chat-search-input{flex:1;border:1.5px solid #E5E7EB;border-radius:8px;padding:7px 10px;font-size:12px;outline:none}
.chat-search-input:focus{border-color:#1A5FB4}
.chat-role-sel{border:1.5px solid #E5E7EB;border-radius:8px;padding:7px 8px;font-size:12px;outline:none;background:#fff}

.chat-user-list{overflow-y:auto;flex:1;padding:8px}
.chat-user-item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:8px;cursor:pointer;transition:background .12s}
.chat-user-item:hover{background:#F3F4F6}
.chat-user-av{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0}
.chat-user-name{font-size:12px;font-weight:600;color:#111827}
.chat-user-sub{font-size:11px;color:#6B7280}

/* Conversation dans le panneau */
.chat-conv-head{padding:10px 14px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;gap:8px;flex-shrink:0;cursor:pointer;background:#F9FAFB}
.chat-conv-head:hover{background:#EFF6FF}
.chat-conv-head .back{font-size:16px;color:#1A5FB4}
.chat-conv-name{font-size:13px;font-weight:700;color:#111827;flex:1}

.chat-messages{flex:1;overflow-y:auto;padding:12px;background:#F8FAFC;display:flex;flex-direction:column;gap:8px}
.cmsg-row{display:flex;flex-direction:column;gap:2px;max-width:80%}
.cmsg-row.me{align-self:flex-end;align-items:flex-end}
.cmsg-row.them{align-self:flex-start}
.cmsg-bubble{padding:8px 12px;border-radius:14px;font-size:12px;line-height:1.5;word-break:break-word}
.cmsg-row.me .cmsg-bubble{background:#1A5FB4;color:#fff;border-bottom-right-radius:3px}
.cmsg-row.them .cmsg-bubble{background:#fff;color:#374151;border-bottom-left-radius:3px;box-shadow:0 1px 3px rgba(0,0,0,.07)}
.cmsg-meta{font-size:10px;color:#9CA3AF}
.cmsg-audio{display:flex;align-items:center;gap:6px;padding:6px 10px;background:rgba(255,255,255,.15);border-radius:8px}
.cmsg-audio audio{height:28px;flex:1}
.cmsg-img{max-width:180px;border-radius:8px}
.cmsg-doc{font-size:11px;text-decoration:none;display:flex;align-items:center;gap:5px}

.chat-input-zone{padding:10px 12px;border-top:1px solid #F3F4F6;background:#fff;flex-shrink:0}
.chat-attach-preview{font-size:11px;color:#1A5FB4;background:#EFF6FF;border-radius:6px;padding:4px 8px;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.chat-attach-preview button{background:none;border:none;color:#EF4444;cursor:pointer;font-size:13px;margin-left:auto;padding:0}
.chat-input-row{display:flex;align-items:flex-end;gap:6px}
.chat-textarea{flex:1;border:1.5px solid #E5E7EB;border-radius:10px;padding:8px 12px;font-size:12px;resize:none;outline:none;min-height:36px;max-height:100px;font-family:inherit}
.chat-textarea:focus{border-color:#1A5FB4}
.chat-icon-btn{width:34px;height:34px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;transition:all .15s}
.chat-icon-btn:hover{border-color:#1A5FB4;background:#EFF6FF}
.chat-icon-btn.rec{border-color:#EF4444;background:#FEE2E2;animation:blink 1s infinite}
.chat-send-btn{width:34px;height:34px;border-radius:8px;border:none;background:#1A5FB4;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.chat-send-btn:hover{background:#0F4A9E}
.rec-bar{font-size:11px;color:#EF4444;font-weight:600;display:none;align-items:center;gap:6px;margin-bottom:6px}
.rec-dot{width:7px;height:7px;background:#EF4444;border-radius:50%;animation:blink 1s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

/* Diffusion modal */
.modal-ov{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1200;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(2px)}
.modal-box{background:#fff;border-radius:16px;padding:28px;width:460px;max-width:95vw;box-shadow:0 8px 30px rgba(0,0,0,.15)}
.modal-title{font-size:16px;font-weight:700;color:#111827;margin:0 0 18px;display:flex;align-items:center;justify-content:space-between}
.modal-title button{background:none;border:none;cursor:pointer;color:#6B7280;font-size:16px}
.form-lbl{font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px}
.form-grp{margin-bottom:14px}
.form-txt{width:100%;padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;font-family:inherit;resize:vertical;min-height:80px;box-sizing:border-box}
.form-txt:focus{border-color:#7C3AED}
.target-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:4px}
.target-opt{padding:9px;border:1.5px solid #E5E7EB;border-radius:8px;cursor:pointer;text-align:center;font-size:12px;font-weight:600;color:#374151;transition:all .13s}
.target-opt:hover{border-color:#7C3AED;color:#7C3AED}
.target-opt.sel{border-color:#7C3AED;background:#EDE9FE;color:#7C3AED}
.btn-bcast{width:100%;padding:10px;background:#7C3AED;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}
.btn-bcast:hover{background:#6D28D9}

/* Flash */
.flash-bar{padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;margin-bottom:14px}
.flash-ok{background:#D1FAE5;color:#065F46;border:1.5px solid #6EE7B7}
.flash-err{background:#FEE2E2;color:#991B1B;border:1.5px solid #FCA5A5}
.flash-bar button{background:none;border:none;cursor:pointer;font-size:14px;color:inherit;margin-left:auto;padding:0}
</style>

{{-- ════ ANCIENNE SUPERVISION ════ --}}
<div class="comm-wrap">

    <div class="comm-header">
        <h1>Communication</h1>
        <p>Supervision des conversations entre utilisateurs de la plateforme</p>
    </div>

    @if($flash)
    <div class="flash-bar {{ $flashType === 'success' ? 'flash-ok' : 'flash-err' }}">
        {{ $flashType === 'success' ? '✅' : '⚠️' }} {{ $flash }}
        <button wire:click="clearFlash">✕</button>
    </div>
    @endif

    {{-- Stats ─────────────────────────────────────────── --}}
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:#EFF6FF">💬</div>
            <div><div class="stat-value">{{ number_format($stats['total']) }}</div><div class="stat-label">Conversations</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#D1FAE5">📨</div>
            <div><div class="stat-value">{{ number_format($stats['messages']) }}</div><div class="stat-label">Messages total</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEF3C7">📅</div>
            <div><div class="stat-value">{{ number_format($stats['today']) }}</div><div class="stat-label">Nouvelles (aujourd'hui)</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEE2E2">🗑️</div>
            <div><div class="stat-value">{{ number_format($stats['flagged']) }}</div><div class="stat-label">Messages supprimés</div></div>
        </div>
    </div>

    {{-- Filtres ─────────────────────────────────────────── --}}
    <div class="filter-bar">
        <input type="text" class="filter-input" placeholder="🔍  Rechercher participant, téléphone…"
               wire:model.live.debounce.300ms="search">
        <select class="filter-select" wire:model.live="typeFilter">
            <option value="">Tous types</option>
            <option value="booking">Réservation</option>
            <option value="support">Support</option>
            <option value="direct">Direct</option>
        </select>
    </div>

    {{-- Liste conversations ─────────────────────────────── --}}
    @if($conversations->isEmpty())
    <div class="empty-state">
        <div class="icon">💬</div>
        <p>Aucune conversation trouvée</p>
    </div>
    @else
    <div class="conv-grid">
        @foreach($conversations as $conv)
        @php
            $parts   = $conv->participants;
            $colors  = ['#1A5FB4','#10B981','#F59E0B','#EF4444','#6366F1','#EC4899'];
            $lastMsg = $conv->messages->first();
            $typeLabels = ['booking'=>'Réservation','support'=>'Support','direct'=>'Direct'];
            $typeBgs    = ['booking'=>'#EFF6FF','support'=>'#EDE9FE','direct'=>'#F3F4F6'];
            $typeColors = ['booking'=>'#1A5FB4','support'=>'#7C3AED','direct'=>'#374151'];
        @endphp
        <div class="conv-item {{ $selectedId === $conv->id ? 'selected' : '' }}"
             wire:click="view({{ $conv->id }})">
            <div class="avatars">
                @foreach($parts->take(2) as $p)
                @php
                    $pName = trim(($p->profile?->first_name??'').(' '.($p->profile?->last_name??''))) ?: $p->phone;
                    $pInit = strtoupper(substr($p->profile?->first_name??'U',0,1).substr($p->profile?->last_name??'',0,1));
                    $pBg   = $colors[abs(crc32($pName))%count($colors)];
                @endphp
                <div class="av" style="background:{{ $pBg }}">{{ $pInit }}</div>
                @endforeach
            </div>
            <div class="conv-content">
                <div class="conv-participants">
                    {{ $parts->map(fn($p) => trim(($p->profile?->first_name??'').(' '.($p->profile?->last_name??''))) ?: $p->phone)->implode(', ') ?: 'Participants inconnus' }}
                </div>
                <div class="conv-last">
                    @if($lastMsg)
                        {{ $lastMsg->sender?->profile?->first_name ?? 'Inconnu' }}: {{ \Illuminate\Support\Str::limit($lastMsg->body ?? '[Pièce jointe]', 50) }}
                    @else
                        Aucun message
                    @endif
                </div>
            </div>
            <div class="conv-meta">
                <span class="conv-time">{{ $conv->updated_at->diffForHumans() }}</span>
                @if($conv->type)
                <span class="badge" style="background:{{ $typeBgs[$conv->type]??'#F3F4F6' }};color:{{ $typeColors[$conv->type]??'#374151' }}">
                    {{ $typeLabels[$conv->type] ?? $conv->type }}
                </span>
                @endif
                <span class="badge" style="background:#F3F4F6;color:#6B7280">{{ $conv->messages_count }} msg</span>
            </div>
        </div>
        @endforeach
    </div>
    {{ $conversations->links() }}
    @endif

</div>{{-- /comm-wrap --}}

{{-- ════ SLIDE-OVER SUPERVISION (ancien) ════ --}}
@if($selectedConv)
@php
    $sParts = $selectedConv->participants;
    $colors2 = ['#1A5FB4','#10B981','#F59E0B','#EF4444','#6366F1','#EC4899'];
    $sNames  = $sParts->map(fn($p) => trim(($p->profile?->first_name??'').(' '.($p->profile?->last_name??'')))?: $p->phone)->implode(', ');
@endphp
<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    <div class="panel-head">
        <div>
            <h2>{{ $sNames ?: 'Conversation' }}</h2>
            <div class="panel-sub">
                {{ $selectedConv->trip ? '🚗 Trajet : '.$selectedConv->trip->departure_city.' → '.$selectedConv->trip->arrival_city : '💬 Support direct' }}
            </div>
        </div>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>
    <div class="msg-list">
        @forelse($selectedConv->messages as $msg)
        @php
            $sName = trim(($msg->sender?->profile?->first_name??'').(' '.($msg->sender?->profile?->last_name??'')))?: ($msg->sender?->phone??'Inconnu');
        @endphp
        <div class="msg-item other">
            <span class="msg-sender" style="font-size:11px;color:#6B7280">{{ $sName }}</span>
            <div class="msg-bubble">
                @if($msg->body){{ $msg->body }}@endif
                @if($msg->attachment_path)
                    @if($msg->attachment_type === 'image')
                    <img src="{{ Storage::disk('public')->url($msg->attachment_path) }}" style="max-width:200px;border-radius:8px;margin-top:4px">
                    @elseif($msg->attachment_type === 'audio')
                    <audio controls src="{{ Storage::disk('public')->url($msg->attachment_path) }}" style="height:28px;margin-top:4px"></audio>
                    @else
                    <a href="{{ Storage::disk('public')->url($msg->attachment_path) }}" target="_blank" style="color:#1A5FB4;font-size:12px">📄 Pièce jointe</a>
                    @endif
                @endif
            </div>
            <div class="msg-meta">
                {{ $msg->created_at->format('d/m H:i') }}
                @if($msg->read_at)<span title="Lu">✓✓</span>@endif
            </div>
        </div>
        @empty
        <div style="text-align:center;padding:40px;color:#9CA3AF;font-size:13px">Aucun message dans cette conversation.</div>
        @endforelse
    </div>
    <div class="panel-footer">Supervision uniquement — utilisez le bouton 💬 pour envoyer un message</div>
</div>
@endif

{{-- ════ BOUTON FLOTTANT CHAT ADMIN ════ --}}
<button class="fab-chat" wire:click="openChat" title="Envoyer un message">
    💬
</button>
<button class="fab-broadcast" wire:click="$set('showBroadcast',true)" title="Diffuser un message">
    📢
</button>

{{-- ════ PANNEAU CHAT ADMIN ════ --}}
@if($showChat)
<div class="chat-panel-overlay" wire:click.self="closeChat">
    <div class="chat-panel">

        {{-- En-tête panneau --}}
        <div class="chat-panel-head">
            @if($chatConv)
            <button class="cp-btn" wire:click="$set('chatConvId', null)">←</button>
            @endif
            <h3>
                @if($chatConv)
                    @php
                        $cOther = $chatConv->participants->first(fn($p) => $p->id !== $adminId);
                        $cName  = trim(($cOther?->profile?->first_name??'').(' '.($cOther?->profile?->last_name??''))) ?: ($cOther?->phone??'Utilisateur');
                    @endphp
                    {{ $cName }}
                @else
                    ✉️ Nouveau message
                @endif
            </h3>
            <button class="cp-btn" wire:click="closeChat">✕</button>
        </div>

        @if(! $chatConv)
        {{-- Recherche d'utilisateur --}}
        <div class="chat-search-area">
            <div class="chat-search-row">
                <input type="text" class="chat-search-input"
                       wire:model.live.debounce.300ms="chatSearch"
                       placeholder="🔍 Nom ou téléphone…">
                <select class="chat-role-sel" wire:model.live="chatRole">
                    <option value="">Tous</option>
                    <option value="driver">Conducteurs</option>
                    <option value="passenger">Passagers</option>
                </select>
            </div>
        </div>

        <div class="chat-user-list">
            @if($chatUsers->isEmpty() && strlen($chatSearch) >= 2)
            <div style="text-align:center;padding:30px;color:#9CA3AF;font-size:12px">Aucun résultat</div>
            @elseif(strlen($chatSearch) < 2)
            <div style="text-align:center;padding:30px;color:#9CA3AF;font-size:12px">Tapez au moins 2 caractères</div>
            @else
            @foreach($chatUsers as $u)
            @php
                $uName = trim(($u->profile?->first_name??'').(' '.($u->profile?->last_name??''))) ?: $u->phone;
                $uInit = strtoupper(substr($u->profile?->first_name??'U',0,1).substr($u->profile?->last_name??'',0,1));
                $uRole = $u->role?->name ?? 'passenger';
                $uBg   = $uRole === 'driver' ? '#10B981' : '#1A5FB4';
            @endphp
            <div class="chat-user-item" wire:click="startChatWith('{{ $u->uuid }}')">
                <div class="chat-user-av" style="background:{{ $uBg }}">{{ $uInit }}</div>
                <div>
                    <div class="chat-user-name">{{ $uName }}</div>
                    <div class="chat-user-sub">{{ $u->phone }} · {{ $uRole === 'driver' ? '🚗 Conducteur' : '👤 Passager' }}</div>
                </div>
                <span style="margin-left:auto;font-size:12px;color:#1A5FB4">→</span>
            </div>
            @endforeach
            @endif
        </div>

        @else
        {{-- Conversation ouverte --}}
        <div class="chat-messages" id="cp-messages">
            @forelse($chatMessages as $msg)
            @php $isMe = $msg->sender_id === $adminId; @endphp
            <div class="cmsg-row {{ $isMe ? 'me' : 'them' }}">
                @if($msg->body)
                <div class="cmsg-bubble">{{ $msg->body }}</div>
                @endif
                @if($msg->attachment_path)
                <div class="cmsg-bubble" style="{{ $isMe ? 'background:#1A5FB4' : 'background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08)' }}">
                    @if($msg->attachment_type === 'image')
                    <img class="cmsg-img" src="{{ Storage::disk('public')->url($msg->attachment_path) }}">
                    @elseif($msg->attachment_type === 'audio')
                    <div class="cmsg-audio">
                        <span>🎙️</span>
                        <audio controls src="{{ Storage::disk('public')->url($msg->attachment_path) }}"></audio>
                    </div>
                    @else
                    <a class="cmsg-doc" href="{{ Storage::disk('public')->url($msg->attachment_path) }}" target="_blank" style="{{ $isMe ? 'color:#fff' : 'color:#374151' }}">
                        📄 {{ basename($msg->attachment_path) }}
                    </a>
                    @endif
                </div>
                @endif
                <div class="cmsg-meta">{{ $msg->created_at->format('H:i') }}@if($isMe && $msg->read_at) ✓✓@endif</div>
            </div>
            @empty
            <div style="text-align:center;padding:30px;color:#9CA3AF;font-size:12px">Dites bonjour 👋</div>
            @endforelse
        </div>

        {{-- Zone de saisie --}}
        <div class="chat-input-zone">
            @if($chatAttachment)
            <div class="chat-attach-preview">
                📎 {{ $chatAttachment->getClientOriginalName() }}
                <button wire:click="$set('chatAttachment', null)">✕</button>
            </div>
            @endif
            <div class="rec-bar" id="cp-rec-bar">
                <div class="rec-dot"></div> Enregistrement…
            </div>
            <div class="chat-input-row">
                <label class="chat-icon-btn" title="Fichier" style="cursor:pointer">
                    📎
                    <input type="file" wire:model="chatAttachment" style="display:none"
                           accept="image/*,application/pdf,.doc,.docx,audio/*">
                </label>
                <button class="chat-icon-btn" id="cp-mic-btn" type="button" title="Audio">🎤</button>
                <textarea class="chat-textarea" wire:model="chatMessage"
                          id="cp-textarea"
                          placeholder="Message…"
                          rows="1"
                          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();$wire.sendChatMessage()}"
                          oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,100)+'px'"></textarea>
                <button class="chat-send-btn" wire:click="sendChatMessage"
                        wire:loading.attr="disabled" wire:target="sendChatMessage">
                    <span wire:loading.remove wire:target="sendChatMessage">➤</span>
                    <span wire:loading wire:target="sendChatMessage">…</span>
                </button>
            </div>
        </div>
        @endif

    </div>
</div>
@endif

{{-- ════ MODAL DIFFUSION ════ --}}
@if($showBroadcast)
<div class="modal-ov" wire:click.self="$set('showBroadcast',false)">
    <div class="modal-box">
        <div class="modal-title">
            📢 Diffuser un message
            <button wire:click="$set('showBroadcast',false)">✕</button>
        </div>

        <div class="form-grp">
            <label class="form-lbl">Destinataires</label>
            <div class="target-grid">
                @foreach([
                    ['tous',             '🌍 Tous'],
                    ['tous_conducteurs', '🚗 Conducteurs'],
                    ['tous_passagers',   '👤 Passagers'],
                    ['en_ligne',         '🟢 En ligne'],
                    ['en_trajet',        '📍 En trajet'],
                ] as [$val, $lbl])
                <div class="target-opt {{ $broadcastTarget === $val ? 'sel' : '' }}"
                     wire:click="$set('broadcastTarget','{{ $val }}')">{{ $lbl }}</div>
                @endforeach
            </div>
        </div>

        <div class="form-grp">
            <label class="form-lbl">Message</label>
            <textarea class="form-txt" wire:model="broadcastMessage"
                      placeholder="Votre message à diffuser…"></textarea>
        </div>

        <button class="btn-bcast" wire:click="sendBroadcast"
                wire:loading.attr="disabled" wire:target="sendBroadcast">
            <span wire:loading.remove wire:target="sendBroadcast">📢 Envoyer la diffusion</span>
            <span wire:loading wire:target="sendBroadcast">Envoi en cours…</span>
        </button>
    </div>
</div>
@endif

@script
<script>
// ── Scroll to bottom quand une conversation est ouverte ──
$wire.on('chat-panel-opened', () => {
    requestAnimationFrame(() => {
        const box = document.getElementById('cp-messages');
        if (box) box.scrollTop = box.scrollHeight;
    });
});

// ── Enregistrement audio MediaRecorder ──────────────────
let recorder    = null;
let chunks      = [];
let audioStream = null;
let isRecording = false;

function setupMic() {
    const btn = document.getElementById('cp-mic-btn');
    const bar = document.getElementById('cp-rec-bar');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        if (!isRecording) {
            try {
                audioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                recorder    = new MediaRecorder(audioStream, { mimeType: 'audio/webm' });
                chunks      = [];

                recorder.ondataavailable = (e) => { if (e.data.size > 0) chunks.push(e.data); };
                recorder.onstop = () => {
                    const blob = new Blob(chunks, { type: 'audio/webm' });
                    const file = new File([blob], `voice-${Date.now()}.webm`, { type: 'audio/webm' });
                    $wire.upload('chatAttachment', file, () => {}, () => alert('Erreur upload audio'), () => {});
                    audioStream.getTracks().forEach(t => t.stop());
                };

                recorder.start();
                isRecording = true;
                btn.classList.add('rec');
                btn.textContent = '⏹';
                if (bar) bar.style.display = 'flex';
            } catch (e) {
                alert('Microphone inaccessible : ' + e.message);
            }
        } else {
            recorder.stop();
            isRecording = false;
            btn.classList.remove('rec');
            btn.textContent = '🎤';
            if (bar) bar.style.display = 'none';
        }
    });
}

// Ré-initialiser le bouton micro à chaque ouverture de conversation
$wire.on('chat-panel-opened', () => { setTimeout(setupMic, 200); });
document.addEventListener('livewire:navigated', setupMic);
setupMic();
</script>
@endscript

</div>
