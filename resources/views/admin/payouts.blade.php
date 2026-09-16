@php
$stLabels = [
    'en_attente'    => ['label' => 'En attente',    'color' => '#F59E0B', 'bg' => '#FEF3C7'],
    'en_traitement' => ['label' => 'En traitement', 'color' => '#3B82F6', 'bg' => '#EFF6FF'],
    'payé'          => ['label' => 'Payé',           'color' => '#10B981', 'bg' => '#D1FAE5'],
    'échoué'        => ['label' => 'Échoué',         'color' => '#EF4444', 'bg' => '#FEE2E2'],
];
$methodLabels = [
    'mtn'     => ['label' => 'MTN MoMo',  'color' => '#D97706', 'bg' => '#FEF3C7'],
    'moov'    => ['label' => 'Moov Money','color' => '#2563EB', 'bg' => '#EFF6FF'],
    'celtiis' => ['label' => 'Celtiis',   'color' => '#7C3AED', 'bg' => '#EDE9FE'],
    'manual'  => ['label' => 'Manuel',    'color' => '#6B7280', 'bg' => '#F3F4F6'],
];
$colors = ['#1A5FB4','#FF7A45','#10B981','#6366F1','#F59E0B','#EF4444','#8B5CF6','#06B6D4'];
@endphp

<div>
<style>
.pay-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.pay-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.pay-header p{font-size:13px;color:#6B7280;margin:0 0 24px}

.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px}
.stat-grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:#fff;border-radius:12px;padding:16px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);cursor:pointer;transition:box-shadow .15s,transform .15s;border:2px solid transparent}
.stat-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.1);transform:translateY(-1px)}
.stat-card.active{border-color:#1A5FB4}
.stat-card.no-click{cursor:default}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-value{font-size:20px;font-weight:700;color:#111827;line-height:1.2}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}

.alert-banner{background:#FEF3C7;border:1.5px solid #FDE68A;border-radius:10px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:13px;color:#92400E}

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
.data-table tr.row-pending td{background:#FFFBEB}

.user-cell{display:flex;align-items:center;gap:10px}
.avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600}
.action-group{display:flex;gap:5px}
.btn-action{width:30px;height:30px;border-radius:7px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s}
.btn-action:hover{border-color:#1A5FB4;background:#EFF6FF}
.empty-state{padding:60px;text-align:center;color:#9CA3AF}

/* Panel */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:480px;max-width:95vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-head h2{font-size:16px;font-weight:700;color:#111827;margin:0}
.panel-close{width:32px;height:32px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:#6B7280;transition:all .15s}
.panel-close:hover{background:#FEE2E2;border-color:#EF4444;color:#EF4444}
.panel-body{flex:1;overflow-y:auto}
.panel-hero{padding:24px;background:linear-gradient(135deg,#1A5FB4,#2563EB);color:#fff}
.panel-content{padding:20px 24px}
.panel-section{margin-bottom:22px}
.panel-section__title{font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px}
.panel-card{background:#F9FAFB;border-radius:10px;padding:14px 16px}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #F3F4F6}
.info-row:last-child{border-bottom:none}
.info-row label{font-size:12px;color:#6B7280}
.info-row span{font-size:13px;font-weight:500;color:#374151;text-align:right}
.panel-footer{padding:16px 24px;border-top:1px solid #F3F4F6;flex-shrink:0}
</style>

<div class="pay-wrap">

    <div class="pay-header">
        <h1>Virements conducteurs</h1>
        <p>Suivi des paiements et reversements aux conducteurs</p>
    </div>

    @if($stats['pending'] > 0)
    <div class="alert-banner">
        ⚠️&nbsp;
        <strong>{{ $stats['pending'] }} virement(s)</strong> en attente de traitement.
    </div>
    @endif

    {{-- Stats row 1 --}}
    <div class="stat-grid">
        <div class="stat-card {{ $statusFilter==='' ? 'active' : '' }}" wire:click="$set('statusFilter','')">
            <div class="stat-icon" style="background:#EFF6FF">💸</div>
            <div><div class="stat-value">{{ number_format($stats['total']) }}</div><div class="stat-label">Total</div></div>
        </div>
        <div class="stat-card {{ $statusFilter==='en_attente' ? 'active' : '' }}" wire:click="$set('statusFilter','en_attente')">
            <div class="stat-icon" style="background:#FEF3C7">⏳</div>
            <div><div class="stat-value" style="{{ $stats['pending']>0?'color:#D97706':'' }}">{{ number_format($stats['pending']) }}</div><div class="stat-label">En attente</div></div>
        </div>
        <div class="stat-card {{ $statusFilter==='payé' ? 'active' : '' }}" wire:click="$set('statusFilter','payé')">
            <div class="stat-icon" style="background:#D1FAE5">✅</div>
            <div><div class="stat-value" style="color:#10B981">{{ number_format($stats['paid']) }}</div><div class="stat-label">Payés</div></div>
        </div>
        <div class="stat-card {{ $statusFilter==='échoué' ? 'active' : '' }}" wire:click="$set('statusFilter','échoué')">
            <div class="stat-icon" style="background:#FEE2E2">❌</div>
            <div><div class="stat-value" style="{{ $stats['failed']>0?'color:#EF4444':'' }}">{{ number_format($stats['failed']) }}</div><div class="stat-label">Échoués</div></div>
        </div>
    </div>

    {{-- Stats row 2 --}}
    <div class="stat-grid-2">
        <div class="stat-card no-click">
            <div class="stat-icon" style="background:#D1FAE5">💰</div>
            <div><div class="stat-value" style="color:#10B981;font-size:16px">{{ number_format($stats['total_paid']) }} F</div><div class="stat-label">Total reversé</div></div>
        </div>
        <div class="stat-card no-click">
            <div class="stat-icon" style="background:#FEF3C7">⏰</div>
            <div><div class="stat-value" style="color:#D97706;font-size:16px">{{ number_format($stats['total_pending']) }} F</div><div class="stat-label">En attente de paiement</div></div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-bar">
        <input class="filter-input" type="text" placeholder="Conducteur, téléphone, référence…"
               wire:model.live.debounce.400ms="search">
        <select class="filter-select" wire:model.live="statusFilter">
            <option value="">Tous les statuts</option>
            @foreach($stLabels as $key => $l)
                <option value="{{ $key }}">{{ $l['label'] }}</option>
            @endforeach
        </select>
        <select class="filter-select" wire:model.live="methodFilter">
            <option value="">Tout opérateur</option>
            @foreach($methodLabels as $key => $ml)
                <option value="{{ $key }}">{{ $ml['label'] }}</option>
            @endforeach
        </select>
        <input class="filter-date" type="date" wire:model.live="dateFrom">
        <input class="filter-date" type="date" wire:model.live="dateTo">
        <button class="btn-reset" wire:click="resetFilters">Réinitialiser</button>
    </div>

    {{-- Table --}}
    <div class="table-wrap">
        <div class="table-head">
            <h2>Virements</h2>
            <span class="table-count">{{ $payouts->total() }} virement(s)</span>
        </div>

        @if($payouts->isEmpty())
            <div class="empty-state"><div style="font-size:40px;margin-bottom:12px">💸</div><div>Aucun virement trouvé</div></div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Conducteur</th>
                        <th>Référence</th>
                        <th>Opérateur</th>
                        <th>Brut</th>
                        <th>Commission</th>
                        <th>Net</th>
                        <th>Trajets</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($payouts as $po)
                    @php
                        $drv  = $po->driver;
                        $dp   = $drv?->profile;
                        $dNm  = trim(($dp?->first_name??'').(' '.($dp?->last_name??''))) ?: ($drv?->phone??'—');
                        $dIn  = strtoupper(substr($dp?->first_name??'D',0,1).substr($dp?->last_name??'',0,1));
                        $dBg  = $colors[abs(crc32($dNm))%count($colors)];
                        $sl   = $stLabels[$po->status??'en_attente'] ?? ['label'=>$po->status,'color'=>'#6B7280','bg'=>'#F3F4F6'];
                        $ml   = $methodLabels[$po->method??'manual'] ?? ['label'=>ucfirst($po->method??'—'),'color'=>'#6B7280','bg'=>'#F3F4F6'];
                    @endphp
                    <tr class="{{ in_array($po->status??'', ['en_attente','en_traitement']) ? 'row-pending' : '' }}">
                        <td>
                            <div class="user-cell">
                                <div class="avatar" style="background:{{ $dBg }}">{{ $dIn }}</div>
                                <div>
                                    <div style="font-weight:600;font-size:12px;color:#111827">{{ $dNm }}</div>
                                    <div style="font-size:10px;color:#9CA3AF">{{ $drv?->phone }}</div>
                                </div>
                            </div>
                        </td>
                        <td><code style="font-size:11px;background:#F3F4F6;padding:2px 6px;border-radius:4px">{{ $po->reference }}</code></td>
                        <td><span class="badge" style="color:{{ $ml['color'] }};background:{{ $ml['bg'] }}">{{ $ml['label'] }}</span></td>
                        <td style="font-size:12px;color:#6B7280">{{ number_format($po->gross_amount) }} F</td>
                        <td style="font-size:12px;color:#EF4444">-{{ number_format($po->commission_amount) }} F</td>
                        <td style="font-weight:700;color:#111827">{{ number_format($po->net_amount) }} F</td>
                        <td style="font-weight:600;text-align:center">{{ $po->trips_count }}</td>
                        <td><span class="badge" style="color:{{ $sl['color'] }};background:{{ $sl['bg'] }}">{{ $sl['label'] }}</span></td>
                        <td style="font-size:11px;color:#9CA3AF">{{ $po->created_at?->format('d/m/Y') }}</td>
                        <td><button class="btn-action" wire:click="view({{ $po->id }})">👁️</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div style="padding:12px 20px;border-top:1px solid #F3F4F6">{{ $payouts->links() }}</div>
        @endif
    </div>

</div>

{{-- Panel --}}
@if($selected)
    @php
        $p    = $selected;
        $slp  = $stLabels[$p->status??'en_attente'] ?? ['label'=>$p->status,'color'=>'#6B7280','bg'=>'#F3F4F6'];
        $mlp  = $methodLabels[$p->method??'manual'] ?? ['label'=>ucfirst($p->method??'—'),'color'=>'#6B7280','bg'=>'#F3F4F6'];
        $dp3  = $p->driver;
        $pp3  = $dp3?->profile;
        $dNm3 = trim(($pp3?->first_name??'').(' '.($pp3?->last_name??''))) ?: ($dp3?->phone??'—');
        $dBg3 = $colors[abs(crc32($dNm3))%count($colors)];
        $dIn3 = strtoupper(substr($pp3?->first_name??'D',0,1).substr($pp3?->last_name??'',0,1));
    @endphp
    <div class="panel-overlay" wire:click="closeView"></div>
    <div class="panel-drawer">
        <div class="panel-head">
            <h2>Virement #{{ $p->id }}</h2>
            <button class="panel-close" wire:click="closeView">✕</button>
        </div>
        <div class="panel-body">
            {{-- Hero --}}
            <div class="panel-hero">
                <div style="font-size:28px;font-weight:700;margin-bottom:4px">{{ number_format($p->net_amount) }} F</div>
                <div style="font-size:12px;opacity:.8;margin-bottom:12px">Net conducteur · {{ $mlp['label'] }}</div>
                <span class="badge" style="background:rgba(255,255,255,.2);color:#fff;font-size:12px">{{ $slp['label'] }}</span>
            </div>

            <div class="panel-content">
                {{-- Conducteur --}}
                <div class="panel-section">
                    <div class="panel-section__title">Conducteur</div>
                    <div class="panel-card">
                        <div style="display:flex;align-items:center;gap:12px;padding-bottom:10px;border-bottom:1px solid #F3F4F6;margin-bottom:8px">
                            <div style="width:40px;height:40px;border-radius:50%;background:{{ $dBg3 }};display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff">{{ $dIn3 }}</div>
                            <div>
                                <div style="font-weight:600;color:#111827">{{ $dNm3 }}</div>
                                <div style="font-size:11px;color:#9CA3AF">{{ $dp3?->phone }}</div>
                            </div>
                        </div>
                        <div class="info-row"><label>Numéro MoMo</label><span style="font-family:monospace">{{ $p->phone_number ?? $dp3?->phone }}</span></div>
                    </div>
                </div>

                {{-- Montants --}}
                <div class="panel-section">
                    <div class="panel-section__title">Détails financiers</div>
                    <div class="panel-card">
                        <div class="info-row"><label>Montant brut</label><span>{{ number_format($p->gross_amount) }} F</span></div>
                        <div class="info-row"><label>Commission plateforme</label><span style="color:#EF4444">-{{ number_format($p->commission_amount) }} F</span></div>
                        <div class="info-row"><label style="font-weight:600">Net à reverser</label><span style="font-size:15px;font-weight:700;color:#1A5FB4">{{ number_format($p->net_amount) }} F</span></div>
                        <div class="info-row"><label>Trajets concernés</label><span>{{ $p->trips_count }}</span></div>
                    </div>
                </div>

                {{-- Infos transaction --}}
                <div class="panel-section">
                    <div class="panel-section__title">Transaction</div>
                    <div class="panel-card">
                        <div class="info-row"><label>Référence</label><span style="font-family:monospace;font-size:11px">{{ $p->reference }}</span></div>
                        <div class="info-row"><label>Opérateur</label><span>{{ $mlp['label'] }}</span></div>
                        <div class="info-row"><label>Statut</label>
                            <span class="badge" style="color:{{ $slp['color'] }};background:{{ $slp['bg'] }}">{{ $slp['label'] }}</span>
                        </div>
                        @if($p->failed_reason)
                        <div class="info-row"><label>Motif d'échec</label><span style="color:#EF4444;font-size:12px">{{ $p->failed_reason }}</span></div>
                        @endif
                        <div class="info-row"><label>Créé le</label><span>{{ $p->created_at?->format('d/m/Y H:i') }}</span></div>
                        @if($p->processed_at)
                        <div class="info-row"><label>Traité le</label><span>{{ $p->processed_at->format('d/m/Y H:i') }}</span></div>
                        @endif
                        @if($p->paid_at)
                        <div class="info-row"><label>Payé le</label><span style="color:#10B981;font-weight:600">{{ $p->paid_at->format('d/m/Y H:i') }}</span></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="panel-footer">
            <button style="width:100%;padding:10px;background:#F3F4F6;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;font-weight:600;color:#374151;cursor:pointer"
                    wire:click="closeView">Fermer</button>
        </div>
    </div>
@endif

</div>
