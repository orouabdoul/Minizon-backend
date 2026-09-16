@php
$statusLabels = [
    'pending'  => ['label' => 'En attente', 'color' => '#F59E0B', 'bg' => '#FEF3C7', 'dot' => '#D97706'],
    'locked'   => ['label' => 'En séquestre','color' => '#1A5FB4', 'bg' => '#EFF6FF',  'dot' => '#1A5FB4'],
    'success'  => ['label' => 'Succès',     'color' => '#10B981', 'bg' => '#D1FAE5', 'dot' => '#059669'],
    'failed'   => ['label' => 'Échoué',     'color' => '#EF4444', 'bg' => '#FEE2E2', 'dot' => '#DC2626'],
    'refunded' => ['label' => 'Remboursé',  'color' => '#6366F1', 'bg' => '#EDE9FE', 'dot' => '#4F46E5'],
];
$providerLabels = [
    'mtn'         => ['label' => 'MTN MoMo',   'color' => '#F59E0B', 'bg' => '#FEF3C7'],
    'moov'        => ['label' => 'Moov Money', 'color' => '#10B981', 'bg' => '#D1FAE5'],
    'celtiis'     => ['label' => 'Celtiis',    'color' => '#6366F1', 'bg' => '#EDE9FE'],
    'fedapay'     => ['label' => 'FedaPay',    'color' => '#1A5FB4', 'bg' => '#EFF6FF'],
    'card'        => ['label' => 'Carte',      'color' => '#374151', 'bg' => '#F3F4F6'],
];
@endphp

<style>
.pay-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.pay-header{margin-bottom:24px}
.pay-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.pay-header p{font-size:13px;color:#6B7280;margin:0}

/* stat cards */
.stat-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:#fff;border-radius:12px;padding:16px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);cursor:pointer;transition:box-shadow .15s,transform .15s;border:2px solid transparent}
.stat-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.1);transform:translateY(-1px)}
.stat-card.active{border-color:#1A5FB4}
.stat-card.no-action{cursor:default}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-value{font-size:20px;font-weight:700;color:#111827;line-height:1.2}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}

/* filter bar */
.filter-bar{background:#fff;border-radius:12px;padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
.filter-input{flex:1;min-width:220px;padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;transition:border-color .15s}
.filter-input:focus{border-color:#1A5FB4}
.filter-select{padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;background:#fff;cursor:pointer;transition:border-color .15s}
.filter-select:focus{border-color:#1A5FB4}
.filter-date{padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;transition:border-color .15s}
.filter-date:focus{border-color:#1A5FB4}
.btn-reset{padding:9px 16px;background:#F3F4F6;border:1.5px solid #E5E7EB;border-radius:8px;font-size:12px;color:#6B7280;cursor:pointer;white-space:nowrap;transition:all .15s}
.btn-reset:hover{background:#E5E7EB;color:#374151}

/* table */
.table-wrap{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);overflow:hidden}
.table-head{padding:14px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between}
.table-head h2{font-size:14px;font-weight:600;color:#374151;margin:0}
.table-count{font-size:12px;color:#9CA3AF}
.data-table{width:100%;border-collapse:collapse}
.data-table th{padding:11px 16px;text-align:left;font-size:11px;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #F3F4F6;white-space:nowrap;background:#FAFAFA}
.data-table td{padding:13px 16px;font-size:13px;color:#374151;border-bottom:1px solid #F9FAFB;vertical-align:middle}
.data-table tr:hover td{background:#F9FAFB}
.data-table tr:last-child td{border-bottom:none}

/* payer info cell */
.payer-cell{display:flex;align-items:center;gap:10px}
.payer-avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}
.payer-info{display:flex;flex-direction:column;gap:1px}
.payer-name{font-weight:600;color:#111827;font-size:12px}
.payer-phone{font-size:11px;color:#9CA3AF}

/* ref cell */
.ref-code{font-family:monospace;font-size:11px;color:#6B7280;background:#F3F4F6;padding:2px 6px;border-radius:4px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block}

/* amounts */
.amount-gross{font-size:14px;font-weight:700;color:#111827}
.amount-net{font-size:11px;color:#10B981}
.amount-comm{font-size:11px;color:#F59E0B}

/* badge */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600}

/* actions */
.action-group{display:flex;gap:6px}
.btn-action{width:30px;height:30px;border-radius:7px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s;color:#374151}
.btn-action:hover{border-color:#1A5FB4;background:#EFF6FF;color:#1A5FB4}

/* empty */
.empty-state{padding:60px;text-align:center;color:#9CA3AF}
.empty-state .icon{font-size:40px;margin-bottom:12px}

/* ── Panel ──────────────────────────────────────────────── */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:480px;max-width:95vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-head h2{font-size:16px;font-weight:700;color:#111827;margin:0}
.panel-close{width:32px;height:32px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:#6B7280;transition:all .15s}
.panel-close:hover{background:#FEE2E2;border-color:#EF4444;color:#EF4444}
.panel-body{flex:1;overflow-y:auto;padding:24px}
.panel-footer{padding:16px 24px;border-top:1px solid #F3F4F6;flex-shrink:0}
.panel-section{margin-bottom:22px}
.panel-section__title{font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px}
.panel-card{background:#F9FAFB;border-radius:10px;padding:14px 16px}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #F3F4F6}
.info-row:last-child{border-bottom:none}
.info-row label{font-size:12px;color:#6B7280}
.info-row span{font-size:13px;font-weight:600;color:#374151}
</style>

<div class="pay-wrap">

    {{-- Header --}}
    <div class="pay-header">
        <h1>Paiements</h1>
        <p>Suivi des transactions et flux financiers de la plateforme</p>
    </div>

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card {{ $statusFilter === '' ? 'active' : '' }}" wire:click="$set('statusFilter','')">
            <div class="stat-icon" style="background:#EFF6FF">💳</div>
            <div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-label">Total</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter === 'success' ? 'active' : '' }}" wire:click="$set('statusFilter','success')">
            <div class="stat-icon" style="background:#D1FAE5">✅</div>
            <div>
                <div class="stat-value" style="color:#10B981">{{ number_format($stats['success']) }}</div>
                <div class="stat-label">Succès</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter === 'pending' ? 'active' : '' }}" wire:click="$set('statusFilter','pending')">
            <div class="stat-icon" style="background:#FEF3C7">⏳</div>
            <div>
                <div class="stat-value" style="{{ $stats['pending'] > 0 ? 'color:#D97706' : '' }}">{{ number_format($stats['pending']) }}</div>
                <div class="stat-label">En attente</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter === 'failed' ? 'active' : '' }}" wire:click="$set('statusFilter','failed')">
            <div class="stat-icon" style="background:#FEE2E2">❌</div>
            <div>
                <div class="stat-value" style="{{ $stats['failed'] > 0 ? 'color:#EF4444' : '' }}">{{ number_format($stats['failed']) }}</div>
                <div class="stat-label">Échoués</div>
            </div>
        </div>
        <div class="stat-card no-action">
            <div class="stat-icon" style="background:#D1FAE5">💰</div>
            <div>
                <div class="stat-value" style="font-size:14px;color:#10B981">{{ number_format($stats['gross_total']) }} F</div>
                <div class="stat-label">Volume total</div>
            </div>
        </div>
        <div class="stat-card no-action">
            <div class="stat-icon" style="background:#FEF3C7">📊</div>
            <div>
                <div class="stat-value" style="font-size:14px;color:#D97706">{{ number_format($stats['commission']) }} F</div>
                <div class="stat-label">Commission</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-bar">
        <input type="text" class="filter-input" placeholder="🔍  Référence, téléphone, nom…"
               wire:model.live.debounce.300ms="search">
        <select class="filter-select" wire:model.live="statusFilter">
            <option value="">Tous statuts</option>
            <option value="pending">En attente</option>
            <option value="locked">En séquestre</option>
            <option value="success">Succès</option>
            <option value="failed">Échoué</option>
            <option value="refunded">Remboursé</option>
        </select>
        @if($providers->isNotEmpty())
        <select class="filter-select" wire:model.live="providerFilter">
            <option value="">Tous opérateurs</option>
            @foreach($providers as $p)
                <option value="{{ $p }}">{{ $providerLabels[$p]['label'] ?? ucfirst($p) }}</option>
            @endforeach
        </select>
        @endif
        <input type="date" class="filter-date" wire:model.live="dateFrom">
        <input type="date" class="filter-date" wire:model.live="dateTo">
        <button class="btn-reset" wire:click="resetFilters">✕ Réinitialiser</button>
    </div>

    {{-- Table --}}
    <div class="table-wrap">
        <div class="table-head">
            <h2>Transactions</h2>
            <span class="table-count">{{ $payments->total() }} transaction(s)</span>
        </div>

        @if($payments->isEmpty())
        <div class="empty-state">
            <div class="icon">💳</div>
            <p>Aucune transaction trouvée</p>
        </div>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Payeur</th>
                    <th>Trajet</th>
                    <th>Opérateur</th>
                    <th>Référence</th>
                    <th>Montants</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($payments as $pay)
                @php
                    $usr       = $pay->user;
                    $prf       = $usr?->profile;
                    $uName     = trim(($prf?->first_name ?? '') . ' ' . ($prf?->last_name ?? '')) ?: ($usr?->phone ?? 'Inconnu');
                    $uInit     = strtoupper(substr($prf?->first_name ?? 'U', 0, 1) . substr($prf?->last_name ?? '', 0, 1)) ?: 'U';
                    $palette   = ['#1A5FB4','#10B981','#F59E0B','#6366F1','#EF4444','#EC4899'];
                    $uBg       = $palette[crc32($uName) % count($palette)];

                    $bk        = $pay->booking;
                    $tr        = $bk?->trip;
                    $route     = $tr ? $tr->departure_city . ' → ' . $tr->arrival_city : '—';

                    $prov      = strtolower($pay->provider ?? '');
                    $provData  = $providerLabels[$prov] ?? ['label' => ucfirst($pay->provider ?? '—'), 'color' => '#6B7280', 'bg' => '#F3F4F6'];

                    $st        = $pay->status ?? 'pending';
                    $stData    = $statusLabels[$st] ?? ['label' => $st, 'color' => '#6B7280', 'bg' => '#F3F4F6'];
                @endphp
                <tr>
                    <td>
                        <div class="payer-cell">
                            <div class="payer-avatar" style="background:{{ $uBg }};color:#fff">{{ $uInit }}</div>
                            <div class="payer-info">
                                <span class="payer-name">{{ $uName }}</span>
                                <span class="payer-phone">{{ $usr?->phone ?? '—' }}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:12px;font-weight:600;color:#374151;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $route }}</div>
                        @if($bk)<div style="font-size:11px;color:#9CA3AF">{{ $bk->seats_booked }} place(s)</div>@endif
                    </td>
                    <td>
                        <span class="badge" style="background:{{ $provData['bg'] }};color:{{ $provData['color'] }}">
                            {{ $provData['label'] }}
                        </span>
                        @if($pay->phone_number)
                        <div style="font-size:10px;color:#9CA3AF;margin-top:2px">{{ $pay->phone_number }}</div>
                        @endif
                    </td>
                    <td>
                        @if($pay->transaction_reference)
                        <span class="ref-code" title="{{ $pay->transaction_reference }}">{{ $pay->transaction_reference }}</span>
                        @else
                        <span style="color:#9CA3AF;font-size:11px">—</span>
                        @endif
                    </td>
                    <td>
                        <div class="amount-gross">{{ number_format($pay->gross_amount ?? 0) }} F</div>
                        @if($pay->commission_amount)
                        <div class="amount-comm">Com: {{ number_format($pay->commission_amount) }} F</div>
                        @endif
                        @if($pay->net_amount)
                        <div class="amount-net">Net: {{ number_format($pay->net_amount) }} F</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge" style="background:{{ $stData['bg'] }};color:{{ $stData['color'] }}">
                            <span style="width:5px;height:5px;border-radius:50%;background:{{ $stData['dot'] ?? $stData['color'] }};display:inline-block"></span>
                            {{ $stData['label'] }}
                        </span>
                    </td>
                    <td style="font-size:11px;color:#6B7280">
                        {{ $pay->created_at?->format('d/m/Y') }}<br>
                        <span style="color:#9CA3AF">{{ $pay->created_at?->format('H:i') }}</span>
                    </td>
                    <td>
                        <button class="btn-action" title="Détails" wire:click="viewPayment({{ $pay->id }})">👁</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="padding:12px 20px;border-top:1px solid #F3F4F6">
            {{ $payments->links() }}
        </div>
        @endif
    </div>

</div>

{{-- ── Slide-over panel ─────────────────────────────────── --}}
@if($selectedPayment)
@php
    $p2    = $selectedPayment;
    $st2   = $p2->status ?? 'pending';
    $stD2  = $statusLabels[$st2] ?? ['label' => $st2, 'color' => '#6B7280', 'bg' => '#F3F4F6'];
    $prov2 = strtolower($p2->provider ?? '');
    $prD2  = $providerLabels[$prov2] ?? ['label' => ucfirst($p2->provider ?? '—'), 'color' => '#6B7280', 'bg' => '#F3F4F6'];

    $usr2  = $p2->user;
    $prf2  = $usr2?->profile;
    $uNm2  = trim(($prf2?->first_name ?? '') . ' ' . ($prf2?->last_name ?? '')) ?: ($usr2?->phone ?? 'Inconnu');

    $bk2   = $p2->booking;
    $tr2   = $bk2?->trip;
    $pax2  = $bk2?->passenger;
    $paxPrf2 = $pax2?->profile;
    $paxNm2  = trim(($paxPrf2?->first_name ?? '') . ' ' . ($paxPrf2?->last_name ?? '')) ?: ($pax2?->phone ?? '—');
@endphp

<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    <div class="panel-head">
        <h2>Détail de la transaction</h2>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>

    <div class="panel-body">

        {{-- Hero montants --}}
        <div style="background:linear-gradient(135deg,#1A5FB4 0%,#0F4A9E 100%);border-radius:12px;padding:20px;color:#fff;margin-bottom:20px;text-align:center">
            <div style="font-size:11px;opacity:.7;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Montant brut</div>
            <div style="font-size:32px;font-weight:800;margin-bottom:10px">{{ number_format($p2->gross_amount ?? 0) }} FCFA</div>
            <div style="display:flex;justify-content:center;gap:20px">
                <div style="text-align:center">
                    <div style="font-size:11px;opacity:.6">Commission</div>
                    <div style="font-size:16px;font-weight:700;color:#FFB59A">{{ number_format($p2->commission_amount ?? 0) }} F</div>
                </div>
                <div style="width:1px;background:rgba(255,255,255,.2)"></div>
                <div style="text-align:center">
                    <div style="font-size:11px;opacity:.6">Net conducteur</div>
                    <div style="font-size:16px;font-weight:700;color:#6EE7B7">{{ number_format($p2->net_amount ?? 0) }} F</div>
                </div>
            </div>
        </div>

        {{-- Statut + opérateur --}}
        <div style="display:flex;gap:10px;margin-bottom:20px">
            <span class="badge" style="background:{{ $stD2['bg'] }};color:{{ $stD2['color'] }};font-size:13px;padding:6px 14px;border-radius:8px;flex:1;justify-content:center">
                {{ $stD2['label'] }}
            </span>
            <span class="badge" style="background:{{ $prD2['bg'] }};color:{{ $prD2['color'] }};font-size:13px;padding:6px 14px;border-radius:8px;flex:1;justify-content:center">
                {{ $prD2['label'] }}
            </span>
        </div>

        {{-- Références --}}
        <div class="panel-section">
            <div class="panel-section__title">Références</div>
            <div class="panel-card">
                <div class="info-row">
                    <label>Référence interne</label>
                    <span style="font-family:monospace;font-size:11px">{{ $p2->transaction_reference ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <label>Référence opérateur</label>
                    <span style="font-family:monospace;font-size:11px">{{ $p2->provider_reference ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <label>Téléphone paiement</label>
                    <span>{{ $p2->phone_number ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <label>Clé idempotence</label>
                    <span style="font-family:monospace;font-size:10px;color:#9CA3AF">{{ $p2->idempotency_key ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <label>Date création</label>
                    <span>{{ $p2->created_at?->format('d/m/Y à H:i:s') }}</span>
                </div>
                <div class="info-row">
                    <label>Dernière mise à jour</label>
                    <span>{{ $p2->updated_at?->format('d/m/Y à H:i:s') }}</span>
                </div>
            </div>
        </div>

        {{-- Payeur --}}
        <div class="panel-section">
            <div class="panel-section__title">Payeur</div>
            <div class="panel-card">
                <div class="info-row">
                    <label>Nom</label>
                    <span>{{ $uNm2 }}</span>
                </div>
                <div class="info-row">
                    <label>Téléphone</label>
                    <span>{{ $usr2?->phone ?? '—' }}</span>
                </div>
            </div>
        </div>

        {{-- Trajet associé --}}
        @if($bk2 && $tr2)
        <div class="panel-section">
            <div class="panel-section__title">Trajet & Réservation</div>
            <div class="panel-card">
                <div class="info-row">
                    <label>Route</label>
                    <span>{{ $tr2->departure_city }} → {{ $tr2->arrival_city }}</span>
                </div>
                <div class="info-row">
                    <label>Départ</label>
                    <span>{{ $tr2->departure_time?->format('d/m/Y H:i') ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <label>Passager</label>
                    <span>{{ $paxNm2 }}</span>
                </div>
                <div class="info-row">
                    <label>Places réservées</label>
                    <span>{{ $bk2->seats_booked }}</span>
                </div>
                <div class="info-row">
                    <label>Prix calculé</label>
                    <span>{{ number_format($bk2->calculated_price ?? 0) }} F × {{ $bk2->seats_booked }}</span>
                </div>
                <div class="info-row">
                    <label>Frais service</label>
                    <span>{{ number_format($bk2->service_fee ?? 0) }} F</span>
                </div>
            </div>
        </div>
        @endif

    </div>

    <div class="panel-footer">
        <button style="width:100%;padding:11px;background:#1A5FB4;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer"
                wire:click="closeView">Fermer</button>
    </div>
</div>
@endif
