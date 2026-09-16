@php
$statusLabels = [
    'new'         => ['label' => 'Nouveau',     'color' => '#1A5FB4', 'bg' => '#EFF6FF'],
    'in_progress' => ['label' => 'En cours',    'color' => '#F59E0B', 'bg' => '#FEF3C7'],
    'resolved'    => ['label' => 'Résolu',      'color' => '#10B981', 'bg' => '#D1FAE5'],
    'closed'      => ['label' => 'Fermé',       'color' => '#6B7280', 'bg' => '#F3F4F6'],
];
$priorityLabels = [
    'low'    => ['label' => 'Faible',  'color' => '#6B7280', 'bg' => '#F3F4F6', 'dot' => '#9CA3AF'],
    'medium' => ['label' => 'Moyen',   'color' => '#1A5FB4', 'bg' => '#EFF6FF', 'dot' => '#1A5FB4'],
    'high'   => ['label' => 'Élevé',   'color' => '#F59E0B', 'bg' => '#FEF3C7', 'dot' => '#D97706'],
    'urgent' => ['label' => 'Urgent',  'color' => '#EF4444', 'bg' => '#FEE2E2', 'dot' => '#DC2626'],
];
$channelLabels = [
    'app'   => '📱 App',
    'email' => '✉️ Email',
    'phone' => '📞 Téléphone',
    'chat'  => '💬 Chat',
];
@endphp

<div>
<style>
.sup-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.sup-header{margin-bottom:24px}
.sup-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.sup-header p{font-size:13px;color:#6B7280;margin:0}

/* alert */
.alert-urgent{background:linear-gradient(90deg,#FEE2E2,#FECACA);border:1px solid #FCA5A5;border-radius:10px;padding:12px 18px;display:flex;align-items:center;gap:10px;margin-bottom:20px;font-size:13px;color:#991B1B}

/* stats */
.stat-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:#fff;border-radius:12px;padding:16px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);cursor:pointer;transition:box-shadow .15s,transform .15s;border:2px solid transparent}
.stat-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.1);transform:translateY(-1px)}
.stat-card.active{border-color:#1A5FB4}
.stat-card.no-action{cursor:default}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-value{font-size:20px;font-weight:700;color:#111827;line-height:1.2}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}

/* filter */
.filter-bar{background:#fff;border-radius:12px;padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
.filter-input{flex:1;min-width:200px;padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;transition:border-color .15s}
.filter-input:focus{border-color:#1A5FB4}
.filter-select{padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;background:#fff;cursor:pointer}
.filter-select:focus{border-color:#1A5FB4}
.btn-reset{padding:9px 16px;background:#F3F4F6;border:1.5px solid #E5E7EB;border-radius:8px;font-size:12px;color:#6B7280;cursor:pointer;white-space:nowrap}
.btn-reset:hover{background:#E5E7EB}

/* table */
.table-wrap{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);overflow:hidden}
.table-head{padding:14px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between}
.table-head h2{font-size:14px;font-weight:600;color:#374151;margin:0}
.table-count{font-size:12px;color:#9CA3AF}
.data-table{width:100%;border-collapse:collapse}
.data-table th{padding:11px 16px;text-align:left;font-size:11px;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #F3F4F6;background:#FAFAFA;white-space:nowrap}
.data-table td{padding:13px 16px;font-size:13px;color:#374151;border-bottom:1px solid #F9FAFB;vertical-align:middle}
.data-table tr:hover td{background:#F9FAFB}
.data-table tr:last-child td{border-bottom:none}
.data-table tr.row-urgent td{background:#FFF5F5}
.data-table tr.row-urgent:hover td{background:#FEE2E2}

/* user cell */
.user-cell{display:flex;align-items:center;gap:10px}
.user-avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}

/* badge */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600}
.prio-dot{width:6px;height:6px;border-radius:50%;display:inline-block;flex-shrink:0}

/* subject */
.subject-cell{max-width:200px}
.subject-text{font-weight:600;color:#111827;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.subject-desc{font-size:11px;color:#9CA3AF;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px}

/* actions */
.action-group{display:flex;gap:6px}
.btn-action{width:30px;height:30px;border-radius:7px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s}
.btn-action:hover{border-color:#1A5FB4;background:#EFF6FF}
.btn-action.success:hover{border-color:#10B981;background:#D1FAE5}

/* empty */
.empty-state{padding:60px;text-align:center;color:#9CA3AF}
.empty-state .icon{font-size:40px;margin-bottom:12px}

/* ── Panel ─────────────────────────────── */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:480px;max-width:95vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-head h2{font-size:16px;font-weight:700;color:#111827;margin:0}
.panel-close{width:32px;height:32px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:#6B7280;transition:all .15s}
.panel-close:hover{background:#FEE2E2;border-color:#EF4444;color:#EF4444}
.panel-body{flex:1;overflow-y:auto;padding:24px}
.panel-footer{padding:16px 24px;border-top:1px solid #F3F4F6;display:flex;gap:8px;flex-shrink:0}
.panel-section{margin-bottom:22px}
.panel-section__title{font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px}
.panel-card{background:#F9FAFB;border-radius:10px;padding:14px 16px}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #F3F4F6}
.info-row:last-child{border-bottom:none}
.info-row label{font-size:12px;color:#6B7280}
.info-row span{font-size:13px;font-weight:500;color:#374151}
.btn-panel{flex:1;padding:10px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all .15s}
</style>

<div class="sup-wrap">

    {{-- Header --}}
    <div class="sup-header">
        <h1>Support</h1>
        <p>Gestion des tickets d'assistance utilisateurs</p>
    </div>

    @if($stats['urgent'] > 0)
    <div class="alert-urgent">
        🚨 <strong>{{ $stats['urgent'] }} ticket(s) URGENT(S)</strong> nécessitent une réponse immédiate
    </div>
    @endif

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card {{ $statusFilter === '' ? 'active' : '' }}" wire:click="$set('statusFilter','')">
            <div class="stat-icon" style="background:#F3F4F6">🎧</div>
            <div>
                <div class="stat-value">{{ $stats['total'] }}</div>
                <div class="stat-label">Total</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter === 'new' ? 'active' : '' }}" wire:click="$set('statusFilter','new')">
            <div class="stat-icon" style="background:#EFF6FF">🆕</div>
            <div>
                <div class="stat-value" style="{{ $stats['new'] > 0 ? 'color:#1A5FB4' : '' }}">{{ $stats['new'] }}</div>
                <div class="stat-label">Nouveaux</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter === 'in_progress' ? 'active' : '' }}" wire:click="$set('statusFilter','in_progress')">
            <div class="stat-icon" style="background:#FEF3C7">⚙️</div>
            <div>
                <div class="stat-value">{{ $stats['in_progress'] }}</div>
                <div class="stat-label">En cours</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter === 'resolved' ? 'active' : '' }}" wire:click="$set('statusFilter','resolved')">
            <div class="stat-icon" style="background:#D1FAE5">✅</div>
            <div>
                <div class="stat-value" style="color:#10B981">{{ $stats['resolved'] }}</div>
                <div class="stat-label">Résolus</div>
            </div>
        </div>
        <div class="stat-card no-action {{ $priorityFilter === 'urgent' ? 'active' : '' }}" wire:click="$set('priorityFilter', $priorityFilter === 'urgent' ? '' : 'urgent')">
            <div class="stat-icon" style="background:#FEE2E2">🚨</div>
            <div>
                <div class="stat-value" style="{{ $stats['urgent'] > 0 ? 'color:#EF4444' : '' }}">{{ $stats['urgent'] }}</div>
                <div class="stat-label">Urgents</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-bar">
        <input type="text" class="filter-input" placeholder="🔍  Rechercher sujet, utilisateur…"
               wire:model.live.debounce.300ms="search">
        <select class="filter-select" wire:model.live="statusFilter">
            <option value="">Tous statuts</option>
            <option value="new">Nouveau</option>
            <option value="in_progress">En cours</option>
            <option value="resolved">Résolu</option>
            <option value="closed">Fermé</option>
        </select>
        <select class="filter-select" wire:model.live="priorityFilter">
            <option value="">Toutes priorités</option>
            <option value="urgent">Urgent</option>
            <option value="high">Élevé</option>
            <option value="medium">Moyen</option>
            <option value="low">Faible</option>
        </select>
        <button class="btn-reset" wire:click="$set('statusFilter',''); $set('priorityFilter',''); $set('search','')">✕ Réinitialiser</button>
    </div>

    {{-- Table --}}
    <div class="table-wrap">
        <div class="table-head">
            <h2>Tickets de support</h2>
            <span class="table-count">{{ $tickets->total() }} ticket(s)</span>
        </div>

        @if($tickets->isEmpty())
        <div class="empty-state">
            <div class="icon">🎧</div>
            <p>Aucun ticket trouvé</p>
        </div>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Utilisateur</th>
                    <th>Sujet</th>
                    <th>Priorité</th>
                    <th>Canal</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($tickets as $tk)
                @php
                    $usr    = $tk->user;
                    $prf    = $usr?->profile;
                    $uName  = trim(($prf?->first_name ?? '') . ' ' . ($prf?->last_name ?? '')) ?: ($usr?->phone ?? 'Inconnu');
                    $uInit  = strtoupper(substr($prf?->first_name ?? 'U', 0, 1) . substr($prf?->last_name ?? '', 0, 1)) ?: 'U';
                    $pal    = ['#1A5FB4','#10B981','#F59E0B','#6366F1','#EF4444','#EC4899'];
                    $uBg    = $pal[abs(crc32($uName)) % count($pal)];
                    $prio   = $priorityLabels[$tk->priority ?? 'medium'] ?? ['label'=>$tk->priority,'color'=>'#6B7280','bg'=>'#F3F4F6','dot'=>'#9CA3AF'];
                    $stD    = $statusLabels[$tk->status ?? 'new'] ?? ['label'=>$tk->status,'color'=>'#6B7280','bg'=>'#F3F4F6'];
                    $isUrgent = ($tk->priority === 'urgent') && !in_array($tk->status, ['resolved','closed']);
                @endphp
                <tr class="{{ $isUrgent ? 'row-urgent' : '' }}">
                    <td style="color:#9CA3AF;font-size:11px">#{{ $tk->id }}</td>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar" style="background:{{ $uBg }};color:#fff">{{ $uInit }}</div>
                            <div>
                                <div style="font-size:12px;font-weight:600;color:#111827">{{ $uName }}</div>
                                <div style="font-size:11px;color:#9CA3AF">{{ $usr?->phone ?? '—' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="subject-cell">
                            <div class="subject-text">{{ $tk->subject ?? '—' }}</div>
                            @if($tk->description)
                            <div class="subject-desc">{{ Str::limit($tk->description, 50) }}</div>
                            @endif
                        </div>
                    </td>
                    <td>
                        <span class="badge" style="background:{{ $prio['bg'] }};color:{{ $prio['color'] }}">
                            <span class="prio-dot" style="background:{{ $prio['dot'] }}"></span>
                            {{ $prio['label'] }}
                        </span>
                    </td>
                    <td style="font-size:12px;color:#6B7280">{{ $channelLabels[$tk->channel] ?? ucfirst($tk->channel ?? '—') }}</td>
                    <td>
                        <span class="badge" style="background:{{ $stD['bg'] }};color:{{ $stD['color'] }}">{{ $stD['label'] }}</span>
                    </td>
                    <td style="font-size:12px;color:#6B7280">{{ $tk->created_at?->format('d/m/Y') }}</td>
                    <td>
                        <div class="action-group">
                            <button class="btn-action" title="Voir" wire:click="viewTicket({{ $tk->id }})">👁</button>
                            @if($tk->status === 'new')
                            <button class="btn-action" title="Prendre en charge"
                                    wire:click="updateStatus({{ $tk->id }}, 'in_progress')">⚙️</button>
                            @endif
                            @if(!in_array($tk->status, ['resolved','closed']))
                            <button class="btn-action success" title="Marquer résolu"
                                    wire:click="updateStatus({{ $tk->id }}, 'resolved')"
                                    wire:confirm="Marquer ce ticket comme résolu ?">✓</button>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="padding:12px 20px;border-top:1px solid #F3F4F6">
            {{ $tickets->links() }}
        </div>
        @endif
    </div>

</div>

{{-- ── Panel ─────────────────────────────── --}}
@if($selectedTicket)
@php
    $tk2   = $selectedTicket;
    $stD2  = $statusLabels[$tk2->status ?? 'new'] ?? ['label'=>$tk2->status,'color'=>'#6B7280','bg'=>'#F3F4F6'];
    $pr2   = $priorityLabels[$tk2->priority ?? 'medium'] ?? ['label'=>$tk2->priority,'color'=>'#6B7280','bg'=>'#F3F4F6','dot'=>'#9CA3AF'];
    $u2    = $tk2->user;
    $p2    = $u2?->profile;
    $uNm2  = trim(($p2?->first_name ?? '') . ' ' . ($p2?->last_name ?? '')) ?: ($u2?->phone ?? 'Inconnu');
    $isOpen2 = in_array($tk2->status, ['new', 'in_progress']);
@endphp

<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    <div class="panel-head">
        <div>
            <h2>Ticket #{{ $tk2->id }}</h2>
            <div style="display:flex;gap:6px;margin-top:4px">
                <span class="badge" style="background:{{ $stD2['bg'] }};color:{{ $stD2['color'] }}">{{ $stD2['label'] }}</span>
                <span class="badge" style="background:{{ $pr2['bg'] }};color:{{ $pr2['color'] }}">
                    <span class="prio-dot" style="background:{{ $pr2['dot'] }}"></span>
                    {{ $pr2['label'] }}
                </span>
            </div>
        </div>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>

    <div class="panel-body">

        {{-- Utilisateur --}}
        <div class="panel-section">
            <div class="panel-section__title">Utilisateur</div>
            <div class="panel-card">
                <div class="info-row"><label>Nom</label><span>{{ $uNm2 }}</span></div>
                <div class="info-row"><label>Téléphone</label><span>{{ $u2?->phone ?? '—' }}</span></div>
                <div class="info-row"><label>Email</label><span>{{ $p2?->email ?? '—' }}</span></div>
            </div>
        </div>

        {{-- Ticket --}}
        <div class="panel-section">
            <div class="panel-section__title">Détails du ticket</div>
            <div class="panel-card">
                <div class="info-row"><label>Canal</label><span>{{ $channelLabels[$tk2->channel] ?? ucfirst($tk2->channel ?? '—') }}</span></div>
                <div class="info-row"><label>Créé le</label><span>{{ $tk2->created_at?->format('d/m/Y à H:i') }}</span></div>
                @if($tk2->resolved_at)
                <div class="info-row"><label>Résolu le</label><span>{{ $tk2->resolved_at->format('d/m/Y à H:i') }}</span></div>
                @endif
            </div>
            <div style="margin-top:10px">
                <div style="font-size:13px;font-weight:700;color:#111827;margin-bottom:6px">{{ $tk2->subject }}</div>
                @if($tk2->description)
                <div style="background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:14px;font-size:13px;color:#374151;line-height:1.6">
                    {{ $tk2->description }}
                </div>
                @endif
            </div>
        </div>

    </div>

    <div class="panel-footer">
        @if($isOpen2)
            @if($tk2->status === 'new')
            <button class="btn-panel" style="background:#EFF6FF;color:#1A5FB4;border:1.5px solid #BFDBFE"
                    wire:click="updateStatus({{ $tk2->id }}, 'in_progress')">⚙️ Prendre en charge</button>
            @endif
            <button class="btn-panel" style="background:#D1FAE5;color:#065F46;border:1.5px solid #6EE7B7"
                    wire:click="updateStatus({{ $tk2->id }}, 'resolved')"
                    wire:confirm="Marquer comme résolu ?">✅ Résoudre</button>
            <button class="btn-panel" style="background:#F3F4F6;color:#374151;border:1.5px solid #D1D5DB"
                    wire:click="updateStatus({{ $tk2->id }}, 'closed')"
                    wire:confirm="Fermer ce ticket ?">🔒 Fermer</button>
        @endif
        <button class="btn-panel" style="background:#1A5FB4;color:#fff;border:none"
                wire:click="closeView">Fermer</button>
    </div>
</div>
@endif
</div>
