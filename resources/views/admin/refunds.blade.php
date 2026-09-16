<div>
<style>
.rf-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.rf-header{margin-bottom:24px}
.rf-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.rf-header p{font-size:13px;color:#6B7280;margin:0}

.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;gap:12px}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-value{font-size:22px;font-weight:700;color:#111827}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px}

.alert-banner{background:#FEF3C7;border-left:4px solid #F59E0B;border-radius:10px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;gap:12px}
.alert-banner .icon{font-size:22px;flex-shrink:0}
.alert-banner-text{font-size:13px;color:#92400E;font-weight:500}
.alert-banner-sub{font-size:12px;color:#B45309;margin-top:2px}

.filter-bar{background:#fff;border-radius:12px;padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:18px}
.filter-input{flex:1;min-width:180px;padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none}
.filter-input:focus{border-color:#1A5FB4}
.filter-select{padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;background:#fff;cursor:pointer}
.filter-select:focus{border-color:#1A5FB4}
.filter-date{padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;background:#fff;cursor:pointer}

.table-wrap{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);overflow:hidden}
.table-head{padding:14px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between}
.table-head h2{font-size:14px;font-weight:600;color:#374151;margin:0}
.table-count{font-size:12px;color:#9CA3AF}
.data-table{width:100%;border-collapse:collapse}
.data-table th{padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #F3F4F6;background:#FAFAFA}
.data-table td{padding:12px 16px;font-size:13px;color:#374151;border-bottom:1px solid #F9FAFB;vertical-align:middle}
.data-table tr:hover td{background:#F9FAFB}
.data-table tr:last-child td{border-bottom:none}

.user-cell{display:flex;align-items:center;gap:8px}
.user-av{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0}
.user-name{font-size:12px;font-weight:600;color:#374151}
.user-phone{font-size:11px;color:#9CA3AF}

.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}
.ref-badge{font-family:monospace;background:#F3F4F6;color:#374151;border-radius:6px;padding:2px 7px;font-size:12px;border:1px solid #E5E7EB}

.operator-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700}

.amount-val{font-size:14px;font-weight:700;color:#111827}
.amount-fcfa{font-size:10px;color:#9CA3AF;text-transform:uppercase}

.action-group{display:flex;gap:5px}
.btn-action{width:28px;height:28px;border-radius:6px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:all .15s}
.btn-action:hover{border-color:#1A5FB4;background:#EFF6FF}
.btn-action.success:hover{border-color:#10B981;background:#D1FAE5}
.btn-action.danger:hover{border-color:#EF4444;background:#FEE2E2}

.empty-state{padding:60px;text-align:center;color:#9CA3AF}
.empty-state .icon{font-size:40px;margin-bottom:12px}

/* ── Panel ───────── */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:460px;max-width:95vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-head h2{font-size:16px;font-weight:700;color:#111827;margin:0}
.panel-close{width:32px;height:32px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:#6B7280;transition:all .15s}
.panel-close:hover{background:#FEE2E2;border-color:#EF4444;color:#EF4444}
.panel-body{flex:1;overflow-y:auto;padding:24px}
.panel-actions{padding:14px 24px;border-top:1px solid #F3F4F6;display:flex;gap:10px;flex-wrap:wrap;flex-shrink:0}
.btn-panel{flex:1;min-width:110px;padding:10px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .15s}
.btn-panel-success{background:#D1FAE5;color:#065F46;border:1.5px solid #6EE7B7}
.btn-panel-success:hover{background:#10B981;color:#fff}
.btn-panel-danger{background:#FEE2E2;color:#EF4444;border:1.5px solid #FCA5A5}
.btn-panel-danger:hover{background:#EF4444;color:#fff}
.btn-panel-warn{background:#FEF3C7;color:#D97706;border:1.5px solid #FCD34D}
.btn-panel-warn:hover{background:#D97706;color:#fff}
.btn-panel-secondary{background:#F3F4F6;color:#374151;border:1.5px solid #E5E7EB}
.btn-panel-secondary:hover{background:#E5E7EB}

.panel-section{margin-bottom:20px}
.panel-section__title{font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px}
.panel-card{background:#F9FAFB;border-radius:10px;padding:14px 16px}
.info-row{display:flex;align-items:flex-start;gap:12px;padding:8px 0;border-bottom:1px solid #F3F4F6}
.info-row:last-child{border-bottom:none}
.info-label{font-size:11px;color:#9CA3AF;min-width:120px;flex-shrink:0;padding-top:1px}
.info-value{font-size:13px;color:#374151;font-weight:500}

.amount-hero{text-align:center;padding:24px;background:linear-gradient(135deg,#1A5FB4,#1A5FB4cc);border-radius:12px;margin-bottom:20px;color:#fff}
.amount-hero-val{font-size:36px;font-weight:800;margin-bottom:4px}
.amount-hero-label{font-size:12px;opacity:.85;text-transform:uppercase;letter-spacing:.8px}
</style>

<div class="rf-wrap">

    <div class="rf-header">
        <h1>Remboursements</h1>
        <p>Gestion des demandes de retrait et remboursements passagers</p>
    </div>

    {{-- Alert banner if pending --}}
    @if($stats['pending'] > 0)
    <div class="alert-banner">
        <div class="icon">⚠️</div>
        <div>
            <div class="alert-banner-text">{{ $stats['pending'] }} demande(s) en attente nécessitent une action</div>
            <div class="alert-banner-sub">Montant total en attente : {{ number_format($stats['total_pend']) }} FCFA</div>
        </div>
    </div>
    @endif

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:#EFF6FF">💳</div>
            <div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-label">Demandes total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEF3C7">⏳</div>
            <div>
                <div class="stat-value" style="{{ $stats['pending'] > 0 ? 'color:#D97706' : '' }}">{{ number_format($stats['pending']) }}</div>
                <div class="stat-label">En attente</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#D1FAE5">✅</div>
            <div>
                <div class="stat-value" style="color:#10B981">{{ number_format($stats['approved']) }}</div>
                <div class="stat-label">Approuvées</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#D1FAE5">💰</div>
            <div>
                <div class="stat-value" style="color:#10B981">{{ number_format($stats['total_paid']) }}</div>
                <div class="stat-label">Total versé (FCFA)</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-bar">
        <input type="text" class="filter-input" placeholder="🔍  Référence, numéro, nom…"
               wire:model.live.debounce.300ms="search">
        <select class="filter-select" wire:model.live="statusFilter">
            <option value="">Tous statuts</option>
            <option value="pending">En attente</option>
            <option value="approved">Approuvée</option>
            <option value="rejected">Rejetée</option>
            <option value="failed">Échouée</option>
        </select>
        <select class="filter-select" wire:model.live="methodFilter">
            <option value="">Tous opérateurs</option>
            <option value="mtn">MTN Mobile Money</option>
            <option value="moov">Moov Money</option>
            <option value="celtiis">Celtiis Cash</option>
        </select>
        <input type="date" class="filter-date" wire:model.live="dateFrom" title="Date début">
        <input type="date" class="filter-date" wire:model.live="dateTo" title="Date fin">
    </div>

    {{-- Table --}}
    <div class="table-wrap">
        <div class="table-head">
            <h2>Demandes de retrait</h2>
            <span class="table-count">{{ $withdrawals->total() }} demande(s)</span>
        </div>

        @if($withdrawals->isEmpty())
        <div class="empty-state">
            <div class="icon">💳</div>
            <p>Aucune demande trouvée</p>
        </div>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Utilisateur</th>
                    <th>Montant</th>
                    <th>Opérateur</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($withdrawals as $w)
            @php
                $colors = ['#1A5FB4','#10B981','#F59E0B','#EF4444','#6366F1','#EC4899'];
                $user = $w->user;
                $uName = trim(($user?->profile?->first_name??'').(' '.($user?->profile?->last_name??''))) ?: ($user?->phone ?? '—');
                $uInit = strtoupper(substr($user?->profile?->first_name??'U',0,1).substr($user?->profile?->last_name??'',0,1));
                $uBg   = $colors[abs(crc32($uName))%count($colors)];

                $opBadge = match($w->provider) {
                    'mtn'     => ['bg'=>'#FEF9C3','c'=>'#713F12','icon'=>'🟡','label'=>'MTN MoMo'],
                    'moov'    => ['bg'=>'#DBEAFE','c'=>'#1E3A8A','icon'=>'🔵','label'=>'Moov Money'],
                    'celtiis' => ['bg'=>'#FCE7F3','c'=>'#9D174D','icon'=>'🔴','label'=>'Celtiis'],
                    default   => ['bg'=>'#F3F4F6','c'=>'#374151','icon'=>'💳','label'=>ucfirst($w->provider ?? '?')],
                };

                $stD = match($w->status) {
                    'pending'  => ['bg'=>'#FEF3C7','c'=>'#92400E','label'=>'En attente'],
                    'approved' => ['bg'=>'#D1FAE5','c'=>'#065F46','label'=>'Approuvée'],
                    'rejected' => ['bg'=>'#FEE2E2','c'=>'#991B1B','label'=>'Rejetée'],
                    'failed'   => ['bg'=>'#F3F4F6','c'=>'#6B7280','label'=>'Échouée'],
                    default    => ['bg'=>'#F3F4F6','c'=>'#374151','label'=>$w->status],
                };
            @endphp
            <tr>
                <td><span class="ref-badge">{{ $w->reference ?? '—' }}</span></td>
                <td>
                    <div class="user-cell">
                        <div class="user-av" style="background:{{ $uBg }}">{{ $uInit }}</div>
                        <div>
                            <div class="user-name">{{ $uName }}</div>
                            <div class="user-phone">{{ $user?->phone ?? '—' }}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="amount-val">{{ number_format($w->amount) }}</div>
                    <div class="amount-fcfa">FCFA</div>
                </td>
                <td>
                    <span class="operator-badge" style="background:{{ $opBadge['bg'] }};color:{{ $opBadge['c'] }}">
                        {{ $opBadge['icon'] }} {{ $opBadge['label'] }}
                    </span>
                </td>
                <td>
                    <span class="badge" style="background:{{ $stD['bg'] }};color:{{ $stD['c'] }}">
                        {{ $stD['label'] }}
                    </span>
                </td>
                <td style="font-size:12px;color:#9CA3AF">{{ $w->created_at->format('d/m/Y') }}</td>
                <td>
                    <div class="action-group">
                        <button class="btn-action" title="Détail" wire:click="view({{ $w->id }})">👁</button>
                        @if($w->status === 'pending')
                        <button class="btn-action success" title="Approuver" wire:click="approve({{ $w->id }})"
                                wire:confirm="Approuver ce retrait de {{ number_format($w->amount) }} FCFA ?">✓</button>
                        <button class="btn-action danger" title="Rejeter" wire:click="reject({{ $w->id }})"
                                wire:confirm="Rejeter cette demande ?">✕</button>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>

        <div style="padding:12px 20px;border-top:1px solid #F3F4F6">
            {{ $withdrawals->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Detail panel --}}
@if($selectedWithdrawal)
@php
    $wd = $selectedWithdrawal;
    $colors2 = ['#1A5FB4','#10B981','#F59E0B','#EF4444','#6366F1','#EC4899'];
    $user2 = $wd->user;
    $uName2 = trim(($user2?->profile?->first_name??'').(' '.($user2?->profile?->last_name??''))) ?: ($user2?->phone ?? 'Inconnu');
    $uInit2 = strtoupper(substr($user2?->profile?->first_name??'U',0,1).substr($user2?->profile?->last_name??'',0,1));
    $uBg2 = $colors2[abs(crc32($uName2))%count($colors2)];

    $opBadge2 = match($wd->provider) {
        'mtn'     => ['bg'=>'#FEF9C3','c'=>'#713F12','icon'=>'🟡','label'=>'MTN Mobile Money'],
        'moov'    => ['bg'=>'#DBEAFE','c'=>'#1E3A8A','icon'=>'🔵','label'=>'Moov Money'],
        'celtiis' => ['bg'=>'#FCE7F3','c'=>'#9D174D','icon'=>'🔴','label'=>'Celtiis Cash'],
        default   => ['bg'=>'#F3F4F6','c'=>'#374151','icon'=>'💳','label'=>ucfirst($wd->provider ?? '?')],
    };
    $stD2 = match($wd->status) {
        'pending'  => ['bg'=>'#FEF3C7','c'=>'#92400E','label'=>'En attente'],
        'approved' => ['bg'=>'#D1FAE5','c'=>'#065F46','label'=>'Approuvée'],
        'rejected' => ['bg'=>'#FEE2E2','c'=>'#991B1B','label'=>'Rejetée'],
        'failed'   => ['bg'=>'#F3F4F6','c'=>'#6B7280','label'=>'Échouée'],
        default    => ['bg'=>'#F3F4F6','c'=>'#374151','label'=>$wd->status],
    };
@endphp
<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    <div class="panel-head">
        <h2>Demande de retrait</h2>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>
    <div class="panel-body">

        {{-- Amount hero --}}
        <div class="amount-hero">
            <div class="amount-hero-val">{{ number_format($wd->amount) }} FCFA</div>
            <div class="amount-hero-label">Montant demandé</div>
        </div>

        {{-- Utilisateur --}}
        <div class="panel-section">
            <div class="panel-section__title">Utilisateur</div>
            <div class="panel-card" style="display:flex;align-items:center;gap:12px">
                <div class="user-av" style="background:{{ $uBg2 }};width:44px;height:44px;font-size:14px">{{ $uInit2 }}</div>
                <div>
                    <div style="font-size:14px;font-weight:600;color:#111827">{{ $uName2 }}</div>
                    <div style="font-size:12px;color:#9CA3AF">{{ $user2?->phone ?? '—' }}</div>
                </div>
            </div>
        </div>

        {{-- Détails du virement --}}
        <div class="panel-section">
            <div class="panel-section__title">Détails du virement</div>
            <div class="panel-card">
                <div class="info-row">
                    <div class="info-label">Référence</div>
                    <div class="info-value"><span class="ref-badge">{{ $wd->reference ?? '—' }}</span></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Opérateur</div>
                    <div class="info-value">
                        <span class="operator-badge" style="background:{{ $opBadge2['bg'] }};color:{{ $opBadge2['c'] }}">
                            {{ $opBadge2['icon'] }} {{ $opBadge2['label'] }}
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Numéro MoMo</div>
                    <div class="info-value">{{ $wd->phone_number ?? '—' }}</div>
                </div>
                @if($wd->bank_name)
                <div class="info-row">
                    <div class="info-label">Banque</div>
                    <div class="info-value">{{ $wd->bank_name }}</div>
                </div>
                @endif
                @if($wd->account_holder_name)
                <div class="info-row">
                    <div class="info-label">Titulaire</div>
                    <div class="info-value">{{ $wd->account_holder_name }}</div>
                </div>
                @endif
                @if($wd->account_number)
                <div class="info-row">
                    <div class="info-label">N° compte</div>
                    <div class="info-value" style="font-family:monospace">{{ $wd->account_number }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Statut et dates --}}
        <div class="panel-section">
            <div class="panel-section__title">Statut & traitement</div>
            <div class="panel-card">
                <div class="info-row">
                    <div class="info-label">Statut</div>
                    <div class="info-value">
                        <span class="badge" style="background:{{ $stD2['bg'] }};color:{{ $stD2['c'] }}">{{ $stD2['label'] }}</span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Demandé le</div>
                    <div class="info-value">{{ $wd->created_at?->format('d/m/Y à H:i') }}</div>
                </div>
                @if($wd->processed_at)
                <div class="info-row">
                    <div class="info-label">Traité le</div>
                    <div class="info-value">{{ \Carbon\Carbon::parse($wd->processed_at)->format('d/m/Y à H:i') }}</div>
                </div>
                @endif
                @if($wd->failed_reason)
                <div class="info-row">
                    <div class="info-label">Raison échec</div>
                    <div class="info-value" style="color:#EF4444">{{ $wd->failed_reason }}</div>
                </div>
                @endif
            </div>
        </div>

    </div>
    <div class="panel-actions">
        @if($wd->status === 'pending')
        <button class="btn-panel btn-panel-success" wire:click="approve({{ $wd->id }})"
                wire:confirm="Approuver ce retrait de {{ number_format($wd->amount) }} FCFA ?">✓ Approuver</button>
        <button class="btn-panel btn-panel-danger" wire:click="reject({{ $wd->id }})"
                wire:confirm="Rejeter cette demande ?">✕ Rejeter</button>
        @endif
        @if(in_array($wd->status, ['approved','pending']))
        <button class="btn-panel btn-panel-warn" wire:click="markFailed({{ $wd->id }})"
                wire:confirm="Marquer ce virement comme échoué ?">⚠ Échoué</button>
        @endif
        <button class="btn-panel btn-panel-secondary" wire:click="closeView">Fermer</button>
    </div>
</div>
@endif
</div>
