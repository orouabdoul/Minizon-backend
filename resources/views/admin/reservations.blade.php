@php
$stLabels = [
    'pending'   => ['label' => 'En attente', 'color' => '#F59E0B', 'bg' => '#FEF3C7'],
    'accepted'  => ['label' => 'Acceptée',   'color' => '#10B981', 'bg' => '#D1FAE5'],
    'rejected'  => ['label' => 'Refusée',    'color' => '#EF4444', 'bg' => '#FEE2E2'],
    'cancelled' => ['label' => 'Annulée',    'color' => '#6B7280', 'bg' => '#F3F4F6'],
];
$payLabels = [
    'pending'       => ['label' => 'Non payé',    'color' => '#F59E0B', 'bg' => '#FEF3C7'],
    'escrow_locked' => ['label' => 'Payé ✓',      'color' => '#10B981', 'bg' => '#D1FAE5'],
    'released'      => ['label' => 'Libéré',      'color' => '#6366F1', 'bg' => '#EDE9FE'],
    'refunded'      => ['label' => 'Remboursé',   'color' => '#EF4444', 'bg' => '#FEE2E2'],
];
@endphp

<div>
<style>
.res-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.res-header{margin-bottom:24px}
.res-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.res-header p{font-size:13px;color:#6B7280;margin:0}

.stat-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:#fff;border-radius:12px;padding:16px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);cursor:pointer;transition:box-shadow .15s,transform .15s;border:2px solid transparent}
.stat-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.1);transform:translateY(-1px)}
.stat-card.active{border-color:#1A5FB4}
.stat-card.no-click{cursor:default}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-value{font-size:20px;font-weight:700;color:#111827;line-height:1.2}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}

.filter-bar{background:#fff;border-radius:12px;padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
.filter-input{flex:1;min-width:200px;padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;transition:border-color .15s}
.filter-input:focus{border-color:#1A5FB4}
.filter-select{padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;background:#fff;cursor:pointer}
.filter-select:focus{border-color:#1A5FB4}
.filter-date{padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none}
.filter-date:focus{border-color:#1A5FB4}
.btn-reset{padding:9px 16px;background:#F3F4F6;border:1.5px solid #E5E7EB;border-radius:8px;font-size:12px;color:#6B7280;cursor:pointer;white-space:nowrap}
.btn-reset:hover{background:#E5E7EB}

.table-wrap{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);overflow:hidden}
.table-head{padding:14px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between}
.table-head h2{font-size:14px;font-weight:600;color:#374151;margin:0}
.table-count{font-size:12px;color:#9CA3AF}
.data-table{width:100%;border-collapse:collapse}
.data-table th{padding:11px 16px;text-align:left;font-size:11px;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #F3F4F6;background:#FAFAFA;white-space:nowrap}
.data-table td{padding:13px 16px;font-size:13px;color:#374151;border-bottom:1px solid #F9FAFB;vertical-align:middle}
.data-table tr:hover td{background:#F9FAFB}
.data-table tr:last-child td{border-bottom:none}

.user-cell{display:flex;align-items:center;gap:10px}
.avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600}

.action-group{display:flex;gap:6px}
.btn-action{width:30px;height:30px;border-radius:7px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s}
.btn-action:hover{border-color:#1A5FB4;background:#EFF6FF}

.empty-state{padding:60px;text-align:center;color:#9CA3AF}
.empty-state .icon{font-size:40px;margin-bottom:12px}

/* Panel */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:480px;max-width:95vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-head h2{font-size:16px;font-weight:700;color:#111827;margin:0}
.panel-close{width:32px;height:32px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:#6B7280;transition:all .15s}
.panel-close:hover{background:#FEE2E2;border-color:#EF4444;color:#EF4444}
.panel-body{flex:1;overflow-y:auto;padding:24px}
.panel-footer{padding:16px 24px;border-top:1px solid #F3F4F6;flex-shrink:0}
.panel-section{margin-bottom:20px}
.panel-section__title{font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px}
.panel-card{background:#F9FAFB;border-radius:10px;padding:14px 16px}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #F3F4F6}
.info-row:last-child{border-bottom:none}
.info-row label{font-size:12px;color:#6B7280}
.info-row span{font-size:13px;font-weight:500;color:#374151}
</style>

<div class="res-wrap">

    <div class="res-header">
        <h1>Réservations</h1>
        <p>Suivi de toutes les réservations passagers</p>
    </div>

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card {{ $statusFilter==='' && $payFilter==='' ? 'active' : '' }}" wire:click="$set('statusFilter',''); $set('payFilter','')">
            <div class="stat-icon" style="background:#EFF6FF">🎫</div>
            <div><div class="stat-value">{{ number_format($stats['total']) }}</div><div class="stat-label">Total</div></div>
        </div>
        <div class="stat-card {{ $statusFilter==='pending' ? 'active' : '' }}" wire:click="$set('statusFilter','pending')">
            <div class="stat-icon" style="background:#FEF3C7">⏳</div>
            <div><div class="stat-value" style="{{ $stats['pending']>0?'color:#D97706':'' }}">{{ number_format($stats['pending']) }}</div><div class="stat-label">En attente</div></div>
        </div>
        <div class="stat-card {{ $statusFilter==='accepted' ? 'active' : '' }}" wire:click="$set('statusFilter','accepted')">
            <div class="stat-icon" style="background:#D1FAE5">✅</div>
            <div><div class="stat-value" style="color:#10B981">{{ number_format($stats['accepted']) }}</div><div class="stat-label">Acceptées</div></div>
        </div>
        <div class="stat-card {{ $payFilter==='escrow_locked' ? 'active' : '' }}" wire:click="$set('payFilter','escrow_locked')">
            <div class="stat-icon" style="background:#D1FAE5">💳</div>
            <div><div class="stat-value" style="color:#10B981">{{ number_format($stats['paid']) }}</div><div class="stat-label">Payées</div></div>
        </div>
        <div class="stat-card no-click">
            <div class="stat-icon" style="background:#EFF6FF">💰</div>
            <div><div class="stat-value" style="font-size:15px;color:#1A5FB4">{{ number_format($stats['revenue']) }} F</div><div class="stat-label">Volume</div></div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-bar">
        <input type="text" class="filter-input" placeholder="🔍  Passager, trajet, téléphone…"
               wire:model.live.debounce.300ms="search">
        <select class="filter-select" wire:model.live="statusFilter">
            <option value="">Tous statuts</option>
            <option value="pending">En attente</option>
            <option value="accepted">Acceptée</option>
            <option value="rejected">Refusée</option>
            <option value="cancelled">Annulée</option>
        </select>
        <select class="filter-select" wire:model.live="payFilter">
            <option value="">Tout paiement</option>
            <option value="escrow_locked">Payé</option>
            <option value="pending">Non payé</option>
            <option value="refunded">Remboursé</option>
        </select>
        <input type="date" class="filter-date" wire:model.live="dateFrom">
        <input type="date" class="filter-date" wire:model.live="dateTo">
        <button class="btn-reset" wire:click="resetFilters">✕ Réinitialiser</button>
    </div>

    {{-- Table --}}
    <div class="table-wrap">
        <div class="table-head">
            <h2>Réservations</h2>
            <span class="table-count">{{ $bookings->total() }} réservation(s)</span>
        </div>

        @if($bookings->isEmpty())
        <div class="empty-state"><div class="icon">🎫</div><p>Aucune réservation trouvée</p></div>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Passager</th>
                    <th>Trajet</th>
                    <th>Départ trajet</th>
                    <th>Places</th>
                    <th>Montant</th>
                    <th>Statut</th>
                    <th>Paiement</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($bookings as $bk)
                @php
                    $pax    = $bk->passenger;
                    $pPrf   = $pax?->profile;
                    $pName  = trim(($pPrf?->first_name??'').(' '.($pPrf?->last_name??''))) ?: ($pax?->phone??'Inconnu');
                    $pInit  = strtoupper(substr($pPrf?->first_name??'P',0,1).substr($pPrf?->last_name??'',0,1))?:'P';
                    $pal    = ['#1A5FB4','#10B981','#F59E0B','#6366F1','#EF4444','#EC4899'];
                    $pBg    = $pal[abs(crc32($pName))%count($pal)];
                    $tr     = $bk->trip;
                    $route  = $tr ? ($tr->departure_city.' → '.$tr->arrival_city) : '—';
                    $stD    = $stLabels[$bk->status??'pending'] ?? ['label'=>$bk->status,'color'=>'#6B7280','bg'=>'#F3F4F6'];
                    $pyD    = $payLabels[$bk->payment_status??'pending'] ?? ['label'=>$bk->payment_status,'color'=>'#6B7280','bg'=>'#F3F4F6'];
                @endphp
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="avatar" style="background:{{ $pBg }};color:#fff">{{ $pInit }}</div>
                            <div>
                                <div style="font-weight:600;font-size:12px;color:#111827">{{ $pName }}</div>
                                <div style="font-size:11px;color:#9CA3AF">{{ $pax?->phone??'—' }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12px;font-weight:600;color:#374151;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $route }}</td>
                    <td style="font-size:12px;color:#6B7280">{{ $tr?->departure_time?->format('d/m/Y H:i')??'—' }}</td>
                    <td style="font-weight:600;text-align:center">{{ $bk->seats_booked }}</td>
                    <td style="font-weight:700;color:#1A5FB4">{{ number_format($bk->total_price??0) }} F</td>
                    <td><span class="badge" style="background:{{ $stD['bg'] }};color:{{ $stD['color'] }}">{{ $stD['label'] }}</span></td>
                    <td><span class="badge" style="background:{{ $pyD['bg'] }};color:{{ $pyD['color'] }}">{{ $pyD['label'] }}</span></td>
                    <td style="font-size:11px;color:#6B7280">{{ $bk->created_at?->format('d/m/Y') }}</td>
                    <td>
                        <button class="btn-action" wire:click="view({{ $bk->id }})">👁</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="padding:12px 20px;border-top:1px solid #F3F4F6">{{ $bookings->links() }}</div>
        @endif
    </div>

</div>

{{-- Panel --}}
@if($selected)
@php
    $s    = $selected;
    $stD2 = $stLabels[$s->status??'pending'] ?? ['label'=>$s->status,'color'=>'#6B7280','bg'=>'#F3F4F6'];
    $pyD2 = $payLabels[$s->payment_status??'pending'] ?? ['label'=>$s->payment_status,'color'=>'#6B7280','bg'=>'#F3F4F6'];
    $pax2 = $s->passenger;
    $pp2  = $pax2?->profile;
    $pNm2 = trim(($pp2?->first_name??'').(' '.($pp2?->last_name??''))) ?: ($pax2?->phone??'Inconnu');
    $tr2  = $s->trip;
    $drv2 = $tr2?->user;
    $dp2  = $drv2?->profile;
    $dNm2 = trim(($dp2?->first_name??'').(' '.($dp2?->last_name??''))) ?: ($drv2?->phone??'Inconnu');
@endphp
<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    <div class="panel-head">
        <h2>Réservation #{{ $s->id }}</h2>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>
    <div class="panel-body">

        {{-- Statuts --}}
        <div style="display:flex;gap:10px;margin-bottom:20px">
            <span class="badge" style="background:{{ $stD2['bg'] }};color:{{ $stD2['color'] }};font-size:13px;padding:7px 14px;border-radius:8px;flex:1;justify-content:center">{{ $stD2['label'] }}</span>
            <span class="badge" style="background:{{ $pyD2['bg'] }};color:{{ $pyD2['color'] }};font-size:13px;padding:7px 14px;border-radius:8px;flex:1;justify-content:center">{{ $pyD2['label'] }}</span>
        </div>

        {{-- Passager --}}
        <div class="panel-section">
            <div class="panel-section__title">Passager</div>
            <div class="panel-card">
                <div class="info-row"><label>Nom</label><span>{{ $pNm2 }}</span></div>
                <div class="info-row"><label>Téléphone</label><span>{{ $pax2?->phone??'—' }}</span></div>
                <div class="info-row"><label>Email</label><span>{{ $pp2?->email??'—' }}</span></div>
            </div>
        </div>

        {{-- Trajet --}}
        @if($tr2)
        <div class="panel-section">
            <div class="panel-section__title">Trajet</div>
            <div class="panel-card">
                <div class="info-row">
                    <label>Route</label>
                    <span>{{ $tr2->departure_city }} → {{ $tr2->arrival_city }}</span>
                </div>
                <div class="info-row"><label>Départ</label><span>{{ $tr2->departure_time?->format('d/m/Y H:i')??'—' }}</span></div>
                <div class="info-row"><label>Conducteur</label><span>{{ $dNm2 }}</span></div>
                <div class="info-row"><label>Tél. conducteur</label><span>{{ $drv2?->phone??'—' }}</span></div>
            </div>
        </div>
        @endif

        {{-- Détails réservation --}}
        <div class="panel-section">
            <div class="panel-section__title">Détails</div>
            <div class="panel-card">
                <div class="info-row"><label>Places réservées</label><span>{{ $s->seats_booked }}</span></div>
                <div class="info-row"><label>Prix calculé</label><span>{{ number_format($s->calculated_price??0) }} F × {{ $s->seats_booked }}</span></div>
                <div class="info-row"><label>Frais service</label><span>{{ number_format($s->service_fee??0) }} F</span></div>
                <div class="info-row">
                    <label>Total</label>
                    <span style="font-weight:700;color:#1A5FB4;font-size:15px">{{ number_format($s->total_price??0) }} F</span>
                </div>
                @if($s->pickup_city)
                <div class="info-row"><label>Montée</label><span>{{ $s->pickup_city }}{{ $s->pickup_neighborhood?', '.$s->pickup_neighborhood:'' }}</span></div>
                @endif
                @if($s->dropoff_city)
                <div class="info-row"><label>Descente</label><span>{{ $s->dropoff_city }}{{ $s->dropoff_neighborhood?', '.$s->dropoff_neighborhood:'' }}</span></div>
                @endif
                <div class="info-row"><label>Réservé le</label><span>{{ $s->created_at?->format('d/m/Y H:i') }}</span></div>
                @if($s->picked_up_at)
                <div class="info-row"><label>Embarqué le</label><span>{{ $s->picked_up_at->format('d/m/Y H:i') }}</span></div>
                @endif
                @if($s->passenger_confirmed_at)
                <div class="info-row"><label>Confirmé le</label><span>{{ $s->passenger_confirmed_at->format('d/m/Y H:i') }}</span></div>
                @endif
            </div>
        </div>

        {{-- Paiement --}}
        @if($s->payment)
        <div class="panel-section">
            <div class="panel-section__title">Paiement</div>
            <div class="panel-card">
                <div class="info-row"><label>Opérateur</label><span>{{ strtoupper($s->payment->provider??'—') }}</span></div>
                <div class="info-row"><label>Référence</label><span style="font-family:monospace;font-size:11px">{{ $s->payment->transaction_reference??'—' }}</span></div>
                <div class="info-row"><label>Statut</label><span>{{ $s->payment->status??'—' }}</span></div>
                <div class="info-row"><label>Montant brut</label><span>{{ number_format($s->payment->gross_amount??0) }} F</span></div>
                <div class="info-row"><label>Commission</label><span>{{ number_format($s->payment->commission_amount??0) }} F</span></div>
                <div class="info-row"><label>Net conducteur</label><span style="color:#10B981;font-weight:600">{{ number_format($s->payment->net_amount??0) }} F</span></div>
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
</div>
