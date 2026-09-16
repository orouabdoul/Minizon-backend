@php
$statusLabels = [
    'pending'             => ['label' => 'En attente',   'color' => '#F59E0B', 'bg' => '#FEF3C7'],
    'investigating'       => ['label' => 'En cours',     'color' => '#1A5FB4', 'bg' => '#EFF6FF'],
    'resolved_reporter'   => ['label' => 'Résolu (req.)', 'color' => '#10B981', 'bg' => '#D1FAE5'],
    'resolved_respondent' => ['label' => 'Résolu (déf.)', 'color' => '#6366F1', 'bg' => '#EDE9FE'],
    'closed'              => ['label' => 'Fermé',        'color' => '#6B7280', 'bg' => '#F3F4F6'],
];
$reasonLabels = [
    'no_show'             => 'No-show conducteur',
    'overcharge'          => 'Surfacturation',
    'unsafe_driving'      => 'Conduite dangereuse',
    'route_change'        => 'Changement de route',
    'bad_behavior'        => 'Comportement inapproprié',
    'payment_issue'       => 'Problème de paiement',
    'other'               => 'Autre',
];
@endphp

<div>
<style>
.disp-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.disp-header{margin-bottom:24px}
.disp-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.disp-header p{font-size:13px;color:#6B7280;margin:0}

/* alert banner */
.alert-banner{background:linear-gradient(90deg,#FEF3C7,#FDE68A);border:1px solid #FCD34D;border-radius:10px;padding:12px 18px;display:flex;align-items:center;gap:10px;margin-bottom:20px;font-size:13px;color:#92400E}
.alert-banner strong{font-weight:700}

/* stats */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:#fff;border-radius:12px;padding:18px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 4px rgba(0,0,0,.06);cursor:pointer;transition:box-shadow .15s,transform .15s;border:2px solid transparent}
.stat-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.1);transform:translateY(-1px)}
.stat-card.active{border-color:#1A5FB4}
.stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.stat-value{font-size:22px;font-weight:700;color:#111827;line-height:1.2}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}

/* filter */
.filter-bar{background:#fff;border-radius:12px;padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
.filter-input{flex:1;min-width:200px;padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;transition:border-color .15s}
.filter-input:focus{border-color:#1A5FB4}
.filter-select{padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;background:#fff;cursor:pointer}
.filter-select:focus{border-color:#1A5FB4}
.btn-reset{padding:9px 16px;background:#F3F4F6;border:1.5px solid #E5E7EB;border-radius:8px;font-size:12px;color:#6B7280;cursor:pointer;white-space:nowrap}
.btn-reset:hover{background:#E5E7EB;color:#374151}

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

/* user cell */
.user-cell{display:flex;align-items:center;gap:10px}
.user-avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}
.user-name{font-weight:600;color:#111827;font-size:12px}
.user-phone{font-size:11px;color:#9CA3AF}

/* badge */
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600}

/* reason chip */
.reason-chip{display:inline-flex;align-items:center;gap:4px;background:#F3F4F6;color:#374151;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:500}

/* actions */
.action-group{display:flex;gap:6px}
.btn-action{width:30px;height:30px;border-radius:7px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s}
.btn-action:hover{border-color:#1A5FB4;background:#EFF6FF}

/* empty */
.empty-state{padding:60px;text-align:center;color:#9CA3AF}
.empty-state .icon{font-size:40px;margin-bottom:12px}

/* ── Panel ─────────────────────────────── */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:500px;max-width:95vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-head h2{font-size:16px;font-weight:700;color:#111827;margin:0}
.panel-close{width:32px;height:32px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:#6B7280;transition:all .15s}
.panel-close:hover{background:#FEE2E2;border-color:#EF4444;color:#EF4444}
.panel-body{flex:1;overflow-y:auto;padding:24px}
.panel-actions{padding:16px 24px;border-top:1px solid #F3F4F6;display:flex;flex-direction:column;gap:10px;flex-shrink:0}
.panel-section{margin-bottom:22px}
.panel-section__title{font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px}
.panel-card{background:#F9FAFB;border-radius:10px;padding:14px 16px}
.info-row{display:flex;justify-content:space-between;align-items:flex-start;padding:7px 0;border-bottom:1px solid #F3F4F6;gap:12px}
.info-row:last-child{border-bottom:none}
.info-row label{font-size:12px;color:#6B7280;flex-shrink:0}
.info-row span{font-size:13px;font-weight:500;color:#374151;text-align:right}
.textarea-notes{width:100%;padding:10px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;resize:vertical;min-height:80px;font-family:inherit;outline:none;transition:border-color .15s}
.textarea-notes:focus{border-color:#1A5FB4}
.btn-resolve{flex:1;padding:10px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all .15s}
.resolve-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
</style>

<div class="disp-wrap">

    {{-- Header --}}
    <div class="disp-header">
        <h1>Litiges</h1>
        <p>Gestion des litiges entre passagers et conducteurs</p>
    </div>

    @if($stats['pending'] > 0)
    <div class="alert-banner">
        ⚠️ <strong>{{ $stats['pending'] }} litige(s) en attente</strong> nécessitent votre attention
    </div>
    @endif

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card {{ $statusFilter === '' ? 'active' : '' }}" wire:click="$set('statusFilter','')">
            <div class="stat-icon" style="background:#F3F4F6">⚖️</div>
            <div>
                <div class="stat-value">{{ $stats['total'] }}</div>
                <div class="stat-label">Total</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter === 'pending' ? 'active' : '' }}" wire:click="$set('statusFilter','pending')">
            <div class="stat-icon" style="background:#FEF3C7">⏳</div>
            <div>
                <div class="stat-value" style="{{ $stats['pending'] > 0 ? 'color:#D97706' : '' }}">{{ $stats['pending'] }}</div>
                <div class="stat-label">En attente</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter === 'investigating' ? 'active' : '' }}" wire:click="$set('statusFilter','investigating')">
            <div class="stat-icon" style="background:#EFF6FF">🔍</div>
            <div>
                <div class="stat-value">{{ $stats['investigating'] }}</div>
                <div class="stat-label">En cours</div>
            </div>
        </div>
        <div class="stat-card" wire:click="$set('statusFilter','resolved_reporter')">
            <div class="stat-icon" style="background:#D1FAE5">✅</div>
            <div>
                <div class="stat-value" style="color:#10B981">{{ $stats['resolved'] }}</div>
                <div class="stat-label">Résolus</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-bar">
        <input type="text" class="filter-input" placeholder="🔍  Rechercher requérant, description…"
               wire:model.live.debounce.300ms="search">
        <select class="filter-select" wire:model.live="statusFilter">
            <option value="">Tous statuts</option>
            <option value="pending">En attente</option>
            <option value="investigating">En cours</option>
            <option value="resolved_reporter">Résolu (requérant)</option>
            <option value="resolved_respondent">Résolu (défendeur)</option>
            <option value="closed">Fermé</option>
        </select>
        @if($reasons->isNotEmpty())
        <select class="filter-select" wire:model.live="reasonFilter">
            <option value="">Tous motifs</option>
            @foreach($reasons as $r)
                <option value="{{ $r }}">{{ $reasonLabels[$r] ?? ucfirst($r) }}</option>
            @endforeach
        </select>
        @endif
        <button class="btn-reset" wire:click="$set('statusFilter',''); $set('reasonFilter',''); $set('search','')">✕ Réinitialiser</button>
    </div>

    {{-- Table --}}
    <div class="table-wrap">
        <div class="table-head">
            <h2>Litiges</h2>
            <span class="table-count">{{ $disputes->total() }} litige(s)</span>
        </div>

        @if($disputes->isEmpty())
        <div class="empty-state">
            <div class="icon">⚖️</div>
            <p>Aucun litige trouvé</p>
        </div>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Requérant</th>
                    <th>Trajet</th>
                    <th>Motif</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($disputes as $d)
                @php
                    $rep     = $d->reporter;
                    $rPrf    = $rep?->profile;
                    $rName   = trim(($rPrf?->first_name ?? '') . ' ' . ($rPrf?->last_name ?? '')) ?: ($rep?->phone ?? 'Inconnu');
                    $rInit   = strtoupper(substr($rPrf?->first_name ?? 'U', 0, 1) . substr($rPrf?->last_name ?? '', 0, 1)) ?: 'U';
                    $palette = ['#1A5FB4','#10B981','#F59E0B','#6366F1','#EF4444','#EC4899'];
                    $rBg     = $palette[abs(crc32($rName)) % count($palette)];

                    $bk      = $d->booking;
                    $tr      = $bk?->trip;
                    $route   = $tr ? ($tr->departure_city . ' → ' . $tr->arrival_city) : '—';

                    $st      = $d->status ?? 'pending';
                    $stData  = $statusLabels[$st] ?? ['label' => $st, 'color' => '#6B7280', 'bg' => '#F3F4F6'];
                    $reason  = $reasonLabels[$d->reason_type] ?? ucfirst($d->reason_type ?? '—');
                @endphp
                <tr>
                    <td style="color:#9CA3AF;font-size:11px">#{{ $d->id }}</td>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar" style="background:{{ $rBg }};color:#fff">{{ $rInit }}</div>
                            <div>
                                <div class="user-name">{{ $rName }}</div>
                                <div class="user-phone">{{ $rep?->phone ?? '—' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:12px;font-weight:600;color:#374151">{{ $route }}</div>
                        @if($tr)<div style="font-size:11px;color:#9CA3AF">{{ $tr->departure_time?->format('d/m/Y') }}</div>@endif
                    </td>
                    <td><span class="reason-chip">{{ $reason }}</span></td>
                    <td>
                        <span class="badge" style="background:{{ $stData['bg'] }};color:{{ $stData['color'] }}">
                            {{ $stData['label'] }}
                        </span>
                    </td>
                    <td style="font-size:12px;color:#6B7280">{{ $d->created_at?->format('d/m/Y') }}</td>
                    <td>
                        <div class="action-group">
                            <button class="btn-action" title="Voir" wire:click="viewDispute({{ $d->id }})">👁</button>
                            @if($d->status === 'pending')
                            <button class="btn-action" title="Prendre en charge"
                                    wire:click="setStatus({{ $d->id }}, 'investigating')"
                                    wire:confirm="Prendre en charge ce litige ?">🔍</button>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="padding:12px 20px;border-top:1px solid #F3F4F6">
            {{ $disputes->links() }}
        </div>
        @endif
    </div>

</div>

{{-- ── Panel ─────────────────────────────── --}}
@if($selectedDispute)
@php
    $sd     = $selectedDispute;
    $stD    = $statusLabels[$sd->status ?? 'pending'] ?? ['label' => $sd->status, 'color' => '#6B7280', 'bg' => '#F3F4F6'];
    $rep2   = $sd->reporter;
    $rPrf2  = $rep2?->profile;
    $rNm2   = trim(($rPrf2?->first_name ?? '') . ' ' . ($rPrf2?->last_name ?? '')) ?: ($rep2?->phone ?? 'Inconnu');
    $bk2    = $sd->booking;
    $tr2    = $bk2?->trip;
    $pax2   = $bk2?->passenger;
    $pNm2   = trim(($pax2?->profile?->first_name ?? '') . ' ' . ($pax2?->profile?->last_name ?? '')) ?: ($pax2?->phone ?? '—');
    $isOpen = in_array($sd->status, ['pending', 'investigating']);
@endphp

<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    <div class="panel-head">
        <div>
            <h2>Litige #{{ $sd->id }}</h2>
            <span class="badge" style="background:{{ $stD['bg'] }};color:{{ $stD['color'] }};margin-top:4px;display:inline-flex">{{ $stD['label'] }}</span>
        </div>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>

    <div class="panel-body">

        {{-- Requérant --}}
        <div class="panel-section">
            <div class="panel-section__title">Requérant</div>
            <div class="panel-card">
                <div class="info-row"><label>Nom</label><span>{{ $rNm2 }}</span></div>
                <div class="info-row"><label>Téléphone</label><span>{{ $rep2?->phone ?? '—' }}</span></div>
            </div>
        </div>

        {{-- Motif & description --}}
        <div class="panel-section">
            <div class="panel-section__title">Détails du litige</div>
            <div class="panel-card">
                <div class="info-row">
                    <label>Motif</label>
                    <span>{{ $reasonLabels[$sd->reason_type] ?? ucfirst($sd->reason_type ?? '—') }}</span>
                </div>
                <div class="info-row">
                    <label>Date dépôt</label>
                    <span>{{ $sd->created_at?->format('d/m/Y à H:i') }}</span>
                </div>
                @if($sd->resolved_at)
                <div class="info-row">
                    <label>Résolu le</label>
                    <span>{{ $sd->resolved_at->format('d/m/Y à H:i') }}</span>
                </div>
                @endif
            </div>
            @if($sd->description)
            <div style="background:#FFFBEB;border:1px solid #FCD34D;border-radius:8px;padding:12px 14px;margin-top:10px;font-size:13px;color:#78350F;line-height:1.5">
                {{ $sd->description }}
            </div>
            @endif
        </div>

        {{-- Trajet --}}
        @if($tr2)
        <div class="panel-section">
            <div class="panel-section__title">Trajet concerné</div>
            <div class="panel-card">
                <div class="info-row">
                    <label>Route</label>
                    <span>{{ $tr2->departure_city }} → {{ $tr2->arrival_city }}</span>
                </div>
                <div class="info-row">
                    <label>Départ</label>
                    <span>{{ $tr2->departure_time?->format('d/m/Y H:i') }}</span>
                </div>
                <div class="info-row">
                    <label>Passager</label>
                    <span>{{ $pNm2 }}</span>
                </div>
                <div class="info-row">
                    <label>Montant réservation</label>
                    <span>{{ number_format($bk2->total_price ?? 0) }} FCFA</span>
                </div>
            </div>
        </div>
        @endif

        {{-- Preuve --}}
        @if($sd->proof_path)
        <div class="panel-section">
            <div class="panel-section__title">Preuve fournie</div>
            <div style="background:#F9FAFB;border-radius:8px;padding:12px;text-align:center">
                @php $proofUrl = str_starts_with($sd->proof_path,'http') ? $sd->proof_path : \Illuminate\Support\Facades\Storage::disk('public')->url($sd->proof_path); @endphp
                <img src="{{ $proofUrl }}" style="max-width:100%;border-radius:6px;max-height:200px;object-fit:contain"
                     onerror="this.parentElement.innerHTML='<span style=\'color:#9CA3AF;font-size:12px\'>Fichier non disponible</span>'">
            </div>
        </div>
        @endif

        {{-- Notes admin --}}
        <div class="panel-section">
            <div class="panel-section__title">Notes de décision</div>
            @if($sd->admin_decision_notes && !$isOpen)
            <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;padding:12px 14px;font-size:13px;color:#065F46">
                {{ $sd->admin_decision_notes }}
            </div>
            @else
            <textarea class="textarea-notes" wire:model="decisionNotes"
                      placeholder="Saisissez vos notes de décision…" rows="3">{{ $decisionNotes }}</textarea>
            <button style="margin-top:6px;padding:7px 14px;background:#EFF6FF;color:#1A5FB4;border:1.5px solid #BFDBFE;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer"
                    wire:click="saveNotes">💾 Enregistrer les notes</button>
            @endif
        </div>

    </div>

    {{-- Actions --}}
    <div class="panel-actions">
        @if($isOpen)
        <div style="font-size:11px;color:#9CA3AF;text-align:center;margin-bottom:2px">Décision finale</div>
        <div class="resolve-grid">
            <button class="btn-resolve" style="background:#D1FAE5;color:#065F46;border:1.5px solid #6EE7B7"
                    wire:click="setStatus({{ $sd->id }}, 'resolved_reporter')"
                    wire:confirm="Résoudre en faveur du requérant ?">
                ✅ Faveur requérant
            </button>
            <button class="btn-resolve" style="background:#EDE9FE;color:#4C1D95;border:1.5px solid #C4B5FD"
                    wire:click="setStatus({{ $sd->id }}, 'resolved_respondent')"
                    wire:confirm="Résoudre en faveur du défendeur ?">
                ✅ Faveur défendeur
            </button>
        </div>
        <button class="btn-resolve" style="background:#F3F4F6;color:#374151;border:1.5px solid #D1D5DB;width:100%"
                wire:click="setStatus({{ $sd->id }}, 'closed')"
                wire:confirm="Fermer ce litige sans décision ?">
            🔒 Fermer sans décision
        </button>
        @if($sd->status === 'pending')
        <button class="btn-resolve" style="background:#EFF6FF;color:#1A5FB4;border:1.5px solid #BFDBFE;width:100%"
                wire:click="setStatus({{ $sd->id }}, 'investigating')">
            🔍 Prendre en charge
        </button>
        @endif
        @endif
        <button style="width:100%;padding:10px;background:#1A5FB4;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer"
                wire:click="closeView">Fermer</button>
    </div>
</div>
@endif
</div>
