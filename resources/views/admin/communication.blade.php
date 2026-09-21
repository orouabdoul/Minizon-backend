<div>
<style>
/* ══ Supervision ════════════════════════════════════════ */
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

/* ── Slide-over supervision ─── WhatsApp style ──────── */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:500;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:560px;max-width:96vw;background:#fff;z-index:501;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;background:#075E54;color:#fff}
.panel-head h2{font-size:15px;font-weight:700;margin:0}
.panel-close{width:30px;height:30px;border-radius:50%;border:none;background:rgba(255,255,255,.15);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;color:#fff}
.panel-close:hover{background:rgba(255,255,255,.28)}
.panel-sub{font-size:11px;opacity:.8;margin-top:2px}
.msg-list{flex:1;overflow-y:auto;padding:12px 16px;display:flex;flex-direction:column;gap:4px;background:#E5DDD5;background-image:url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3C/svg%3E")}
.msg-item{max-width:75%;display:flex;flex-direction:column;gap:1px}
.msg-item.other{align-self:flex-start}
.msg-item.mine{align-self:flex-end;align-items:flex-end}
.msg-sender{font-size:11.5px;font-weight:600;color:#128C7E;margin-bottom:2px;padding-left:2px}
.msg-item.mine .msg-sender{color:#075E54;text-align:right;padding-right:2px}
.msg-bubble{padding:6px 10px 4px;border-radius:7.5px;font-size:13.5px;line-height:1.5;word-break:break-word;box-shadow:0 1px 0.5px rgba(11,20,26,.18);position:relative}
.msg-item.other .msg-bubble{background:#fff;color:#111B21;border-top-left-radius:0}
.msg-item.other .msg-bubble::before{content:"";position:absolute;top:0;left:-8px;border:8px solid transparent;border-right-color:#fff;border-top:0;border-left:0}
.msg-item.mine .msg-bubble{background:#DCF8C6;color:#111B21;border-top-right-radius:0}
.msg-item.mine .msg-bubble::after{content:"";position:absolute;top:0;right:-8px;border:8px solid transparent;border-left-color:#DCF8C6;border-top:0;border-right:0}
.msg-meta{font-size:11px;color:#667781;display:flex;align-items:center;gap:4px;justify-content:flex-end;margin-top:1px;padding-right:2px}
.panel-footer{padding:10px 18px;border-top:1px solid #E9EDEF;background:#F0F2F5;flex-shrink:0;text-align:center;font-size:12px;color:#667781}

/* ══ FAB + panneau chat admin ═══════════════════════════ */
.fab-wrap{position:fixed;bottom:28px;right:28px;z-index:800;display:flex;flex-direction:column;align-items:center;gap:10px}
.fab{width:52px;height:52px;border-radius:50%;border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:22px;box-shadow:0 4px 16px rgba(0,0,0,.25);transition:all .2s}
.fab:hover{transform:scale(1.08)}
.fab-main{background:#1A5FB4;box-shadow:0 4px 16px rgba(26,95,180,.45)}
.fab-bcast{background:#7C3AED;width:42px;height:42px;font-size:18px;box-shadow:0 3px 12px rgba(124,58,237,.4)}
.fab-tooltip{font-size:10px;color:#fff;background:rgba(0,0,0,.5);padding:2px 7px;border-radius:6px;white-space:nowrap}

/* Panneau chat — WhatsApp style */
.cp-overlay{position:fixed;inset:0;background:rgba(0,0,0,.25);z-index:900}
.cp{position:fixed;bottom:0;right:0;width:390px;max-width:96vw;height:560px;max-height:88vh;background:#fff;z-index:901;display:flex;flex-direction:column;border-radius:16px 16px 0 0;box-shadow:-2px -4px 24px rgba(0,0,0,.18)}

.cp-head{background:#075E54;color:#fff;padding:11px 14px;display:flex;align-items:center;gap:10px;border-radius:16px 16px 0 0;flex-shrink:0}
.cp-head-title{font-size:13px;font-weight:700;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cp-head-sub{font-size:10px;opacity:.8;margin-top:1px}
.cp-hbtn{width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.15);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.cp-hbtn:hover{background:rgba(255,255,255,.28)}

/* Zone recherche */
.cp-search{padding:10px 12px;border-bottom:1px solid #F3F4F6;flex-shrink:0}
.cp-search-row{display:flex;gap:7px}
.cp-sinput{flex:1;border:1.5px solid #E5E7EB;border-radius:8px;padding:7px 10px;font-size:12px;outline:none;font-family:inherit}
.cp-sinput:focus{border-color:#1A5FB4}
.cp-srole{border:1.5px solid #E5E7EB;border-radius:8px;padding:7px 8px;font-size:12px;outline:none;background:#fff;cursor:pointer}

.cp-user-list{flex:1;overflow-y:auto;padding:8px}
.cp-user{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;transition:background .1s}
.cp-user:hover{background:#F3F4F6}
.cp-uav{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0}
.cp-uname{font-size:12px;font-weight:600;color:#111827}
.cp-usub{font-size:11px;color:#6B7280}

/* Messages dans le panneau — WhatsApp style */
.cp-messages{flex:1;overflow-y:auto;padding:10px 12px;background:#E5DDD5;display:flex;flex-direction:column;gap:4px}
.cpm-row{display:flex;flex-direction:column;gap:1px;max-width:80%}
.cpm-row.me{align-self:flex-end;align-items:flex-end}
.cpm-row.them{align-self:flex-start}
.cpm-bub{padding:6px 10px 4px;border-radius:7.5px;font-size:13px;line-height:1.5;word-break:break-word;box-shadow:0 1px 0.5px rgba(11,20,26,.18);position:relative}
.cpm-row.me .cpm-bub{background:#DCF8C6;color:#111B21;border-top-right-radius:0}
.cpm-row.me .cpm-bub::after{content:"";position:absolute;top:0;right:-8px;border:8px solid transparent;border-left-color:#DCF8C6;border-top:0;border-right:0}
.cpm-row.them .cpm-bub{background:#fff;color:#111B21;border-top-left-radius:0}
.cpm-row.them .cpm-bub::before{content:"";position:absolute;top:0;left:-8px;border:8px solid transparent;border-right-color:#fff;border-top:0;border-left:0}
.cpm-meta{font-size:10.5px;color:#667781;margin-top:1px}
.cpm-tick{color:#53BDEB}
.cpm-audio{display:flex;align-items:center;gap:6px;padding:5px 8px;background:rgba(255,255,255,.15);border-radius:8px}
.cpm-audio audio{height:26px;flex:1;min-width:0}
.cpm-img{max-width:170px;border-radius:8px;display:block;margin-top:3px}
.cpm-doc{font-size:11px;display:flex;align-items:center;gap:5px;text-decoration:none}

/* Zone saisie — WhatsApp style */
.cp-input{padding:8px 10px;border-top:none;background:#F0F2F5;flex-shrink:0}
.cp-attach-prev{font-size:11px;color:#128C7E;background:#E8F5E9;border-radius:6px;padding:4px 8px;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.cp-attach-prev button{background:none;border:none;color:#EF4444;cursor:pointer;font-size:12px;margin-left:auto;padding:0}
.cp-rec-bar{font-size:11px;color:#EF4444;font-weight:600;display:none;align-items:center;gap:5px;margin-bottom:5px}
.cp-rdot{width:7px;height:7px;background:#EF4444;border-radius:50%;animation:cpblink 1s infinite}
@keyframes cpblink{0%,100%{opacity:1}50%{opacity:.25}}
.cp-irow{display:flex;align-items:flex-end;gap:6px}
.cp-ta{flex:1;border:none;border-radius:21px;padding:8px 14px;font-size:13px;resize:none;outline:none;min-height:36px;max-height:100px;font-family:inherit;line-height:1.4;background:#fff;color:#111B21}
.cp-ta::placeholder{color:#8696A0}
.cp-ibtn{width:36px;height:36px;border-radius:50%;border:none;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;transition:all .12s;color:#54656F}
.cp-ibtn:hover{background:#E9EDEF}
.cp-ibtn.rec{background:#FEE2E2;color:#EF4444;animation:cpblink 1s infinite}
.cp-send{width:40px;height:40px;border-radius:50%;border:none;background:#25D366;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;transition:background .12s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.cp-send:hover{background:#22C55E}
.cp-send:disabled{background:#9CA3AF;cursor:not-allowed}

/* Alerte pas d'admin user */
.no-admin-alert{margin:12px;padding:10px 12px;background:#FEF3C7;border:1.5px solid #FCD34D;border-radius:8px;font-size:11px;color:#92400E;line-height:1.5}

/* Modal diffusion */
.modal-ov{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(2px)}
.modal-box{background:#fff;border-radius:16px;padding:26px;width:450px;max-width:95vw;box-shadow:0 8px 30px rgba(0,0,0,.15)}
.modal-title{font-size:15px;font-weight:700;color:#111827;margin:0 0 16px;display:flex;align-items:center;justify-content:space-between}
.modal-title button{background:none;border:none;cursor:pointer;color:#6B7280;font-size:15px}
.flbl{font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px}
.fgrp{margin-bottom:13px}
.ftxt{width:100%;padding:8px 11px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;font-family:inherit;resize:vertical;min-height:76px;box-sizing:border-box}
.ftxt:focus{border-color:#7C3AED}
.tgrid{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-bottom:4px}
.topt{padding:8px;border:1.5px solid #E5E7EB;border-radius:8px;cursor:pointer;text-align:center;font-size:12px;font-weight:600;color:#374151;transition:all .12s;user-select:none}
.topt:hover{border-color:#7C3AED;color:#7C3AED}
.topt.sel{border-color:#7C3AED;background:#EDE9FE;color:#7C3AED}
.btn-bcast{width:100%;padding:10px;background:#7C3AED;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .12s}
.btn-bcast:hover{background:#6D28D9}
.btn-bcast:disabled{background:#9CA3AF;cursor:not-allowed}

/* Flash */
.flash-bar{padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;margin-bottom:14px}
.flash-ok{background:#D1FAE5;color:#065F46;border:1.5px solid #6EE7B7}
.flash-err{background:#FEE2E2;color:#991B1B;border:1.5px solid #FCA5A5}
.flash-bar button{background:none;border:none;cursor:pointer;font-size:14px;color:inherit;margin-left:auto;padding:0;line-height:1}
</style>

{{-- ════ ANCIENNE SUPERVISION ════ --}}
<div class="comm-wrap">

    <div class="comm-header">
        <h1>Communication</h1>
        <p>Supervision des conversations entre utilisateurs de la plateforme</p>
    </div>

    @if($flash)
    <div class="flash-bar {{ $flashType === 'success' ? 'flash-ok' : 'flash-err' }}">
        {{ $flash }}
        <button wire:click="clearFlash">✕</button>
    </div>
    @endif

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
            <div><div class="stat-value">{{ number_format($stats['today']) }}</div><div class="stat-label">Nouvelles aujourd'hui</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEE2E2">🗑️</div>
            <div><div class="stat-value">{{ number_format($stats['flagged']) }}</div><div class="stat-label">Messages supprimés</div></div>
        </div>
    </div>

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

    @if($conversations->isEmpty())
    <div class="empty-state">
        <div class="icon">💬</div>
        <p>Aucune conversation trouvée</p>
    </div>
    @else
    <div class="conv-grid">
        @foreach($conversations as $conv)
        @php
            $parts  = $conv->participants;
            $colors = ['#1A5FB4','#10B981','#F59E0B','#EF4444','#6366F1','#EC4899'];
            $lastMsg = $conv->lastMessage;
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
                    @php
                        $senderName = trim(($lastMsg->sender?->profile?->first_name??'').(' '.($lastMsg->sender?->profile?->last_name??'')))
                                   ?: ($lastMsg->sender?->phone ?? 'Inconnu');
                    @endphp
                        {{ $senderName }}: {{ \Illuminate\Support\Str::limit($lastMsg->body ?? '[Pièce jointe]', 50) }}
                    @else Aucun message @endif
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

</div>

{{-- ════ SLIDE-OVER SUPERVISION ════ --}}
@if($selectedConv)
@php
    $sParts  = $selectedConv->participants;
    $sNames  = $sParts->map(fn($p) => trim(($p->profile?->first_name??'').(' '.($p->profile?->last_name??'')))?: $p->phone)->implode(', ');
    $leftId  = $sParts->first()?->id; // premier participant → gauche
@endphp
<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    <div class="panel-head">
        <div>
            <h2>{{ $sNames ?: 'Conversation' }}</h2>
            <div class="panel-sub">
                {{ $selectedConv->trip ? '🚗 '.$selectedConv->trip->departure_city.' → '.$selectedConv->trip->arrival_city : '💬 Support direct' }}
            </div>
        </div>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>
    <div class="msg-list">
        @forelse($selectedConv->messages as $msg)
        @php
            $sName  = trim(($msg->sender?->profile?->first_name??'').(' '.($msg->sender?->profile?->last_name??'')))?: ($msg->sender?->phone??'Inconnu');
            $isMine = $msg->sender_id !== $leftId; // second participant → droite
        @endphp
        <div class="msg-item {{ $isMine ? 'mine' : 'other' }}">
            <span class="msg-sender">{{ $sName }}</span>
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
            <div class="msg-meta">{{ $msg->created_at->format('d/m H:i') }}</div>
        </div>
        @empty
        <div style="text-align:center;padding:40px;color:#9CA3AF;font-size:13px">Aucun message.</div>
        @endforelse
    </div>
    <div class="panel-footer">Supervision uniquement — utilisez le bouton 💬 pour envoyer</div>
</div>
@endif

{{-- ════ BOUTONS FLOTTANTS ════ --}}
<div class="fab-wrap">
    <button class="fab fab-bcast" wire:click="$set('showBroadcast',true)" title="Diffuser un message">
        📢
    </button>
    <button class="fab fab-main" wire:click="openChat" title="Envoyer un message direct">
        💬
    </button>
</div>

{{-- ════ PANNEAU CHAT ADMIN ════ --}}
@if($showChat)
<div class="cp-overlay" wire:click.self="closeChat"></div>
<div class="cp">

    {{-- En-tête --}}
    <div class="cp-head">
        @if($chatTargetUuid)
        <button class="cp-hbtn" wire:click="backToSearch" title="Retour">←</button>
        @endif
        <div style="flex:1;min-width:0">
            <div class="cp-head-title">
                @if($chatTargetUser)
                    @php
                        $ctName = trim(($chatTargetUser->profile?->first_name??'').(' '.($chatTargetUser->profile?->last_name??''))) ?: ($chatTargetUser->phone??'Utilisateur');
                        $ctRole = $chatTargetUser->role?->name ?? 'passenger';
                    @endphp
                    {{ $ctName }}
                @else
                    ✉️ Nouveau message
                @endif
            </div>
            @if($chatTargetUser)
            <div class="cp-head-sub">
                {{ $ctRole === 'driver' ? '🚗 Conducteur' : '👤 Passager' }} · {{ $chatTargetUser->phone }}
            </div>
            @endif
        </div>
        <button class="cp-hbtn" wire:click="closeChat" title="Fermer">✕</button>
    </div>

    @if(! $chatTargetUuid)
    {{-- ── Recherche d'utilisateur ── --}}
    <div class="cp-search">
        <div class="cp-search-row">
            <input type="text" class="cp-sinput"
                   wire:model.live.debounce.300ms="chatSearch"
                   placeholder="🔍 Nom ou téléphone…"
                   autofocus>
            <select class="cp-srole" wire:model.live="chatRole">
                <option value="">Tous</option>
                <option value="driver">Conducteurs</option>
                <option value="passenger">Passagers</option>
            </select>
        </div>
    </div>

    <div class="cp-user-list">
        @if(strlen($chatSearch) < 2)
        <div style="text-align:center;padding:28px;color:#9CA3AF;font-size:12px">
            Tapez au moins 2 caractères pour rechercher
        </div>
        @elseif($chatUsers->isEmpty())
        <div style="text-align:center;padding:28px;color:#9CA3AF;font-size:12px">
            Aucun résultat pour "{{ $chatSearch }}"
        </div>
        @else
        @foreach($chatUsers as $u)
        @php
            $uName = trim(($u->profile?->first_name??'').(' '.($u->profile?->last_name??''))) ?: $u->phone;
            $uInit = strtoupper(substr($u->profile?->first_name??'U',0,1).substr($u->profile?->last_name??'',0,1));
            $uRole = $u->role?->name ?? 'passenger';
            $uBg   = $uRole === 'driver' ? '#10B981' : '#1A5FB4';
        @endphp
        <div class="cp-user" wire:click="startChatWith('{{ $u->uuid }}')">
            <div class="cp-uav" style="background:{{ $uBg }}">{{ $uInit }}</div>
            <div style="flex:1;min-width:0">
                <div class="cp-uname">{{ $uName }}</div>
                <div class="cp-usub">{{ $u->phone }} · {{ $uRole === 'driver' ? '🚗 Conducteur' : '👤 Passager' }}</div>
            </div>
            <span style="color:#1A5FB4;font-size:13px">→</span>
        </div>
        @endforeach
        @endif
    </div>

    @else
    {{-- ── Conversation ── --}}

    {{-- Alerte si pas d'admin user --}}
    @if(! $hasAdminUser)
    <div class="no-admin-alert">
        ⚠️ <strong>Envoi désactivé :</strong> aucun utilisateur avec le rôle <code>admin</code> dans la table <code>users</code>.<br>
        Créez-en un via la page <strong>Utilisateurs</strong> ou lancez :<br>
        <code style="font-size:10px">php artisan tinker</code> → créer un User avec role_id = id du rôle admin.
    </div>
    @endif

    {{-- Messages --}}
    <div class="cp-messages" id="cp-msg-box">
        @if($chatMessages->isEmpty())
        <div style="text-align:center;padding:28px;color:#9CA3AF;font-size:12px">
            Dites bonjour 👋<br><span style="font-size:10px">Premier message — une nouvelle conversation sera créée</span>
        </div>
        @else
        @foreach($chatMessages as $msg)
        @php $isMe = $msg->sender_id === $adminId; @endphp
        <div class="cpm-row {{ $isMe ? 'me' : 'them' }}">
            @if($msg->body)
            <div class="cpm-bub">{{ $msg->body }}</div>
            @endif
            @if($msg->attachment_path)
            <div class="cpm-bub" style="padding:8px">
                @if($msg->attachment_type === 'image')
                <img class="cpm-img" src="{{ Storage::disk('public')->url($msg->attachment_path) }}">
                @elseif($msg->attachment_type === 'audio')
                <div class="cpm-audio">
                    <span>🎙️</span>
                    <audio controls src="{{ Storage::disk('public')->url($msg->attachment_path) }}"></audio>
                </div>
                @else
                <a class="cpm-doc" href="{{ Storage::disk('public')->url($msg->attachment_path) }}" target="_blank"
                   style="{{ $isMe ? 'color:#fff' : 'color:#374151' }}">
                    📄 {{ basename($msg->attachment_path) }}
                </a>
                @endif
            </div>
            @endif
            <div class="cpm-meta">
                {{ $msg->created_at->format('H:i') }}
                @if($isMe)<span class="cpm-tick">{{ $msg->read_at ? ' ✓✓' : ' ✓' }}</span>@endif
            </div>
        </div>
        @endforeach
        @endif
    </div>

    {{-- Zone de saisie --}}
    <div class="cp-input">
        @if($chatAttachment)
        <div class="cp-attach-prev">
            📎 {{ $chatAttachment->getClientOriginalName() }}
            <button wire:click="$set('chatAttachment', null)">✕</button>
        </div>
        @endif
        <div class="cp-rec-bar" id="cp-rec-bar">
            <div class="cp-rdot"></div> Enregistrement en cours…
        </div>
        <div class="cp-irow">
            <label class="cp-ibtn" title="Joindre un fichier" style="cursor:pointer">
                📎
                <input type="file" wire:model="chatAttachment" style="display:none"
                       accept="image/*,application/pdf,.doc,.docx,audio/*">
            </label>
            <button class="cp-ibtn" id="cp-mic" type="button" title="Enregistrer audio">🎤</button>
            <textarea class="cp-ta"
                      wire:model="chatMessage"
                      id="cp-ta"
                      placeholder="Message…"
                      rows="1"
                      {{ ! $hasAdminUser ? 'disabled' : '' }}
                      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();$wire.sendChatMessage()}"
                      oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,90)+'px'"></textarea>
            <button class="cp-send"
                    wire:click="sendChatMessage"
                    wire:loading.attr="disabled"
                    wire:target="sendChatMessage"
                    {{ ! $hasAdminUser ? 'disabled' : '' }}>
                <span wire:loading.remove wire:target="sendChatMessage">➤</span>
                <span wire:loading wire:target="sendChatMessage" style="font-size:10px">…</span>
            </button>
        </div>
    </div>

    @endif {{-- /chatTargetUuid --}}
</div>
@endif {{-- /showChat --}}

{{-- ════ MODAL DIFFUSION ════ --}}
@if($showBroadcast)
<div class="modal-ov" wire:click.self="$set('showBroadcast',false)">
    <div class="modal-box">
        <div class="modal-title">
            📢 Diffuser un message
            <button wire:click="$set('showBroadcast',false)">✕</button>
        </div>

        @if(! $hasAdminUser)
        <div class="no-admin-alert" style="margin:0 0 14px">
            ⚠️ Aucun utilisateur avec le rôle <code>admin</code> dans la table <code>users</code>. La diffusion ne fonctionnera pas.
        </div>
        @endif

        <div class="fgrp">
            <label class="flbl">Destinataires</label>
            <div class="tgrid">
                @foreach([
                    ['tous',             '🌍 Tous'],
                    ['tous_conducteurs', '🚗 Conducteurs'],
                    ['tous_passagers',   '👤 Passagers'],
                    ['en_ligne',         '🟢 En ligne'],
                    ['en_trajet',        '📍 En trajet'],
                ] as [$v, $l])
                <div class="topt {{ $broadcastTarget === $v ? 'sel' : '' }}"
                     wire:click="$set('broadcastTarget','{{ $v }}')">{{ $l }}</div>
                @endforeach
            </div>
        </div>

        <div class="fgrp">
            <label class="flbl">Message</label>
            <textarea class="ftxt" wire:model="broadcastMessage"
                      placeholder="Votre message à diffuser…"></textarea>
            @error('broadcastMessage')<div style="font-size:11px;color:#EF4444;margin-top:4px">{{ $message }}</div>@enderror
        </div>

        <button class="btn-bcast"
                wire:click="sendBroadcast"
                wire:loading.attr="disabled"
                wire:target="sendBroadcast"
                {{ ! $hasAdminUser ? 'disabled' : '' }}>
            <span wire:loading.remove wire:target="sendBroadcast">📢 Envoyer la diffusion</span>
            <span wire:loading wire:target="sendBroadcast">Envoi en cours…</span>
        </button>
    </div>
</div>
@endif

@script
<script>
// ── Scroll vers le bas à chaque ouverture de conversation ──────────────────
$wire.on('chat-panel-opened', () => {
    requestAnimationFrame(() => {
        const box = document.getElementById('cp-msg-box');
        if (box) box.scrollTop = box.scrollHeight;
    });
});

// ── Enregistrement audio MediaRecorder ────────────────────────────────────
let recorder     = null;
let chunks       = [];
let audioStream  = null;
let isRecording  = false;

function setupMic() {
    const btn = document.getElementById('cp-mic');
    const bar = document.getElementById('cp-rec-bar');
    if (!btn || btn._setup) return;
    btn._setup = true;

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
                    $wire.upload('chatAttachment', file,
                        () => {},
                        () => alert('Erreur lors de l\'upload audio.'),
                        () => {}
                    );
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

// Initialiser le micro à chaque fois que la vue conversation s'ouvre
$wire.on('chat-panel-opened', () => setTimeout(setupMic, 150));
setupMic();
</script>
@endscript

</div>
