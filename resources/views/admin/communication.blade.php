<div>
<style>
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

/* ── Slide-over panel (conversation viewer) ───────── */
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
</style>

<div class="comm-wrap">

    <div class="comm-header">
        <h1>Communication</h1>
        <p>Supervision des conversations entre utilisateurs de la plateforme</p>
    </div>

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:#EFF6FF">💬</div>
            <div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-label">Conversations</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#D1FAE5">📨</div>
            <div>
                <div class="stat-value">{{ number_format($stats['messages']) }}</div>
                <div class="stat-label">Messages total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEF3C7">📅</div>
            <div>
                <div class="stat-value">{{ number_format($stats['today']) }}</div>
                <div class="stat-label">Nouvelles (aujourd'hui)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEE2E2">🗑️</div>
            <div>
                <div class="stat-value">{{ number_format($stats['flagged']) }}</div>
                <div class="stat-label">Messages supprimés</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
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

    {{-- Conversation list --}}
    @if($conversations->isEmpty())
    <div class="empty-state">
        <div class="icon">💬</div>
        <p>Aucune conversation trouvée</p>
    </div>
    @else
    <div class="conv-grid">
        @foreach($conversations as $conv)
        @php
            $parts = $conv->participants;
            $colors = ['#1A5FB4','#10B981','#F59E0B','#EF4444','#6366F1','#EC4899'];
            $lastMsg = $conv->messages->first();
            $typeLabels = ['booking'=>'Réservation','support'=>'Support','direct'=>'Direct'];
            $typeBgs = ['booking'=>'#EFF6FF','support'=>'#EDE9FE','direct'=>'#F3F4F6'];
            $typeColors = ['booking'=>'#1A5FB4','support'=>'#7C3AED','direct'=>'#374151'];
        @endphp
        <div class="conv-item {{ $selectedId === $conv->id ? 'selected' : '' }}"
             wire:click="view({{ $conv->id }})">
            <div class="avatars">
                @foreach($parts->take(2) as $i => $p)
                @php
                    $pName = trim(($p->profile?->first_name??'').(' '.($p->profile?->last_name??''))) ?: $p->phone;
                    $pInit = strtoupper(substr($p->profile?->first_name??'U',0,1).substr($p->profile?->last_name??'',0,1));
                    $pBg = $colors[abs(crc32($pName))%count($colors)];
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
                <span class="conv-time">{{ $conv->updated_at->diffForHumans(null, true, true) }}</span>
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

    <div style="padding:12px 0;display:flex;justify-content:flex-end">
        {{ $conversations->links() }}
    </div>
    @endif

</div>

{{-- Conversation viewer panel --}}
@if($selectedConv)
@php
    $cv = $selectedConv;
    $cvParts = $cv->participants;
    $colors2 = ['#1A5FB4','#10B981','#F59E0B','#EF4444','#6366F1','#EC4899'];
    $cvTitle = $cvParts->map(fn($p) => trim(($p->profile?->first_name??'').(' '.($p->profile?->last_name??''))) ?: $p->phone)->implode(' · ') ?: 'Conversation';
    $firstPart = $cvParts->first();
@endphp
<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    <div class="panel-head">
        <div>
            <h2>{{ \Illuminate\Support\Str::limit($cvTitle, 45) }}</h2>
            <div class="panel-sub">
                {{ $cv->messages->count() }} message(s) · {{ $cv->type ?? 'direct' }}
                @if($cv->trip) · Trajet {{ $cv->trip->departure_city }} → {{ $cv->trip->arrival_city }} @endif
            </div>
        </div>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>

    <div class="msg-list">
        @if($cv->messages->isEmpty())
        <div style="text-align:center;padding:40px;color:#9CA3AF;font-size:13px">Aucun message</div>
        @else
        @foreach($cv->messages->sortBy('created_at') as $msg)
        @php
            $sender = $msg->sender;
            $senderName = trim(($sender?->profile?->first_name??'').(' '.($sender?->profile?->last_name??''))) ?: ($sender?->phone ?? 'Inconnu');
            $senderInit = strtoupper(substr($sender?->profile?->first_name??'U',0,1).substr($sender?->profile?->last_name??'',0,1));
            $senderBg = $colors2[abs(crc32($senderName))%count($colors2)];
            $isMine = $firstPart && $sender?->id === $firstPart->id;
        @endphp
        <div class="msg-item {{ $isMine ? 'mine' : 'other' }}">
            <div class="msg-meta">
                @if(!$isMine)
                <span class="msg-sender">{{ $senderName }}</span> ·
                @endif
                {{ $msg->created_at?->format('H:i') }}
            </div>
            <div class="msg-bubble">
                @if($msg->body){{ $msg->body }}@endif
                @if($msg->attachment_path)
                <div style="margin-top:4px;font-size:11px;opacity:.8">📎 Pièce jointe ({{ $msg->attachment_type ?? 'fichier' }})</div>
                @endif
            </div>
        </div>
        @endforeach
        @endif
    </div>

    <div class="panel-footer">
        👁️ Mode lecture seule — Supervision admin
    </div>
</div>
@endif
</div>
