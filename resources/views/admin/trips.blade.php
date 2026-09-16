@php
    $statusLabels = [
        'pending'   => ['label' => 'En attente', 'color' => '#F59E0B', 'bg' => '#FEF3C7'],
        'active'    => ['label' => 'En cours',   'color' => '#10B981', 'bg' => '#D1FAE5'],
        'completed' => ['label' => 'Terminé',    'color' => '#6366F1', 'bg' => '#EDE9FE'],
        'cancelled' => ['label' => 'Annulé',     'color' => '#EF4444', 'bg' => '#FEE2E2'],
    ];
    $payLabels = [
        'escrow_locked' => ['label' => 'Payé',      'color' => '#10B981', 'bg' => '#D1FAE5'],
        'released'      => ['label' => 'Libéré',    'color' => '#6366F1', 'bg' => '#EDE9FE'],
        'pending'       => ['label' => 'En attente','color' => '#F59E0B', 'bg' => '#FEF3C7'],
        'refunded'      => ['label' => 'Remboursé', 'color' => '#EF4444', 'bg' => '#FEE2E2'],
    ];
@endphp

<style>
/* ── Trips page ─────────────────────────────────────────── */
.trips-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.trips-header{margin-bottom:24px}
.trips-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.trips-header p{font-size:13px;color:#6B7280;margin:0}

/* stat cards */
.stat-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:#fff;border-radius:12px;padding:18px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 4px rgba(0,0,0,.06);cursor:pointer;transition:box-shadow .15s,transform .15s;border:2px solid transparent}
.stat-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.1);transform:translateY(-1px)}
.stat-card.active{border-color:#1A5FB4}
.stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.stat-body{}
.stat-value{font-size:22px;font-weight:700;color:#111827;line-height:1.2}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}

/* filter bar */
.filter-bar{background:#fff;border-radius:12px;padding:16px 20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
.filter-input{flex:1;min-width:200px;padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;transition:border-color .15s}
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

/* route cell */
.route-cell{display:flex;flex-direction:column;gap:2px}
.route-main{font-weight:600;color:#111827;font-size:13px}
.route-sub{font-size:11px;color:#9CA3AF}
.route-arrow{color:#FF7A45;margin:0 4px}

/* driver cell */
.driver-cell{display:flex;align-items:center;gap:10px}
.driver-avatar{width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #E5E7EB}
.driver-avatar-init{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;border:2px solid #E5E7EB}
.driver-info{display:flex;flex-direction:column;gap:1px}
.driver-name{font-weight:600;color:#111827;font-size:12px}
.driver-phone{font-size:11px;color:#9CA3AF}

/* badge */
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600}
.badge-flag{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:11px;background:#FEF3C7;color:#D97706;font-weight:600}

/* seats */
.seats-bar{width:60px;height:5px;background:#E5E7EB;border-radius:3px;overflow:hidden;margin-top:3px}
.seats-fill{height:100%;border-radius:3px;background:#10B981}
.seats-fill.low{background:#EF4444}
.seats-fill.mid{background:#F59E0B}

/* actions */
.action-group{display:flex;gap:6px;align-items:center}
.btn-action{width:30px;height:30px;border-radius:7px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s;color:#374151}
.btn-action:hover{border-color:#1A5FB4;background:#EFF6FF;color:#1A5FB4}
.btn-action.danger:hover{border-color:#EF4444;background:#FEE2E2;color:#EF4444}
.btn-action.warn:hover{border-color:#F59E0B;background:#FEF3C7;color:#D97706}
.btn-action.success:hover{border-color:#10B981;background:#D1FAE5;color:#10B981}

/* empty */
.empty-state{padding:60px;text-align:center;color:#9CA3AF}
.empty-state .icon{font-size:40px;margin-bottom:12px}
.empty-state p{font-size:14px}

/* ── Slide-over panel ───────────────────────────────────── */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:520px;max-width:95vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-head h2{font-size:16px;font-weight:700;color:#111827;margin:0}
.panel-close{width:32px;height:32px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:#6B7280;transition:all .15s}
.panel-close:hover{background:#FEE2E2;border-color:#EF4444;color:#EF4444}
.panel-body{flex:1;overflow-y:auto;padding:24px}
.panel-section{margin-bottom:24px}
.panel-section__title{font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.7px;margin-bottom:12px}
.panel-card{background:#F9FAFB;border-radius:10px;padding:14px 16px}

/* route hero */
.route-hero{background:linear-gradient(135deg,#1A5FB4 0%,#0F4A9E 100%);border-radius:12px;padding:20px;color:#fff;margin-bottom:20px}
.route-hero-main{font-size:18px;font-weight:700;display:flex;align-items:center;gap:8px;margin-bottom:6px}
.route-hero-arrow{color:#FF7A45}
.route-hero-meta{display:flex;gap:16px;flex-wrap:wrap}
.route-hero-meta span{font-size:12px;opacity:.85;display:flex;align-items:center;gap:4px}

/* info grid */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.info-item{}
.info-item label{font-size:10px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:2px}
.info-item span{font-size:13px;font-weight:600;color:#374151}

/* booking list */
.booking-item{background:#F9FAFB;border-radius:8px;padding:12px 14px;margin-bottom:8px;display:flex;align-items:center;gap:12px}
.booking-item:last-child{margin-bottom:0}
.booking-pax-avatar{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0}
.booking-pax-info{flex:1}
.booking-pax-name{font-size:12px;font-weight:600;color:#374151}
.booking-pax-detail{font-size:11px;color:#9CA3AF}
.booking-amount{font-size:13px;font-weight:700;color:#1A5FB4}

/* panel actions */
.panel-actions{padding:16px 24px;border-top:1px solid #F3F4F6;display:flex;gap:10px;flex-shrink:0}
.btn-panel{flex:1;padding:11px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .15s}
.btn-panel-primary{background:#1A5FB4;color:#fff}
.btn-panel-primary:hover{background:#1352A3}
.btn-panel-danger{background:#FEE2E2;color:#EF4444;border:1.5px solid #FCA5A5}
.btn-panel-danger:hover{background:#EF4444;color:#fff}
.btn-panel-warn{background:#FEF3C7;color:#D97706;border:1.5px solid #FCD34D}
.btn-panel-warn:hover{background:#D97706;color:#fff}
</style>

<div class="trips-wrap">

    {{-- Header --}}
    <div class="trips-header">
        <h1>Trajets</h1>
        <p>Gérez et surveillez tous les trajets de la plateforme</p>
    </div>

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card {{ $statusFilter === '' ? 'active' : '' }}" wire:click="$set('statusFilter','')">
            <div class="stat-icon" style="background:#EFF6FF">🗺️</div>
            <div class="stat-body">
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-label">Total trajets</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter === 'active' ? 'active' : '' }}" wire:click="$set('statusFilter','active')">
            <div class="stat-icon" style="background:#D1FAE5">🚗</div>
            <div class="stat-body">
                <div class="stat-value">{{ number_format($stats['active']) }}</div>
                <div class="stat-label">En cours</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter === 'completed' ? 'active' : '' }}" wire:click="$set('statusFilter','completed')">
            <div class="stat-icon" style="background:#EDE9FE">✅</div>
            <div class="stat-body">
                <div class="stat-value">{{ number_format($stats['completed']) }}</div>
                <div class="stat-label">Terminés</div>
            </div>
        </div>
        <div class="stat-card {{ $flagFilter === 'flagged' ? 'active' : '' }}" wire:click="$set('flagFilter', $flagFilter === 'flagged' ? '' : 'flagged')">
            <div class="stat-icon" style="background:#FEF3C7">🚩</div>
            <div class="stat-body">
                <div class="stat-value" style="{{ $stats['flagged'] > 0 ? 'color:#D97706' : '' }}">{{ number_format($stats['flagged']) }}</div>
                <div class="stat-label">Signalés</div>
            </div>
        </div>
        <div class="stat-card" style="cursor:default">
            <div class="stat-icon" style="background:#FEE2E2">💰</div>
            <div class="stat-body">
                <div class="stat-value" style="font-size:16px">{{ number_format($stats['revenue']) }} F</div>
                <div class="stat-label">Revenus totaux</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-bar">
        <input type="text" class="filter-input" placeholder="🔍  Rechercher route, conducteur, téléphone…"
               wire:model.live.debounce.300ms="search">
        <select class="filter-select" wire:model.live="statusFilter">
            <option value="">Tous statuts</option>
            <option value="pending">En attente</option>
            <option value="active">En cours</option>
            <option value="completed">Terminé</option>
            <option value="cancelled">Annulé</option>
        </select>
        @if($cities->isNotEmpty())
        <select class="filter-select" wire:model.live="cityFilter">
            <option value="">Toutes villes</option>
            @foreach($cities as $city)
                <option value="{{ $city }}">{{ $city }}</option>
            @endforeach
        </select>
        @endif
        <input type="date" class="filter-date" wire:model.live="dateFrom" title="Depuis">
        <input type="date" class="filter-date" wire:model.live="dateTo" title="Jusqu'au">
        <button class="btn-reset" wire:click="resetFilters">✕ Réinitialiser</button>
    </div>

    {{-- Table --}}
    <div class="table-wrap">
        <div class="table-head">
            <h2>Liste des trajets</h2>
            <span class="table-count">{{ $trips->total() }} trajet(s)</span>
        </div>

        @if($trips->isEmpty())
        <div class="empty-state">
            <div class="icon">🗺️</div>
            <p>Aucun trajet trouvé</p>
        </div>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Route</th>
                    <th>Conducteur</th>
                    <th>Départ</th>
                    <th>Statut</th>
                    <th>Places</th>
                    <th>Prix/place</th>
                    <th>Réservations</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($trips as $trip)
                @php
                    $driver     = $trip->user;
                    $dProfile   = $driver?->profile;
                    $dName      = trim(($dProfile?->first_name ?? '') . ' ' . ($dProfile?->last_name ?? '')) ?: 'Inconnu';
                    $dInitials  = strtoupper(substr($dProfile?->first_name ?? 'U', 0, 1) . substr($dProfile?->last_name ?? '', 0, 1)) ?: 'U';
                    $colors     = ['#1A5FB4','#10B981','#F59E0B','#6366F1','#EF4444','#EC4899'];
                    $dBg        = $colors[abs(crc32($dName)) % count($colors)];
                    $dPhotoPath = $dProfile?->profile_photo;
                    $dPhotoUrl  = $dPhotoPath ? (str_starts_with($dPhotoPath,'http') ? $dPhotoPath : \Illuminate\Support\Facades\Storage::disk('public')->url($dPhotoPath)) : null;

                    $st         = $trip->status ?? 'pending';
                    $stData     = $statusLabels[$st] ?? ['label'=>$st,'color'=>'#6B7280','bg'=>'#F3F4F6'];

                    $bookCount  = $trip->bookings->count();
                    $seatsUsed  = $trip->total_seats - $trip->available_seats;
                    $seatsPct   = $trip->total_seats > 0 ? ($seatsUsed / $trip->total_seats) * 100 : 0;
                    $seatClass  = $seatsPct >= 80 ? 'low' : ($seatsPct >= 50 ? 'mid' : '');
                @endphp
                <tr>
                    {{-- Route --}}
                    <td>
                        <div class="route-cell">
                            <div class="route-main">
                                {{ $trip->departure_city }}
                                <span class="route-arrow">→</span>
                                {{ $trip->arrival_city }}
                            </div>
                            <div class="route-sub">
                                @if($trip->distance_km){{ number_format($trip->distance_km, 1) }} km@endif
                                @if($trip->is_flagged) &nbsp; 🚩@endif
                                @if(!$trip->is_published) &nbsp; 📝@endif
                            </div>
                        </div>
                    </td>

                    {{-- Driver --}}
                    <td>
                        <div class="driver-cell">
                            @if($dPhotoUrl)
                                <img src="{{ $dPhotoUrl }}" alt="{{ $dInitials }}" class="driver-avatar"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            @endif
                            <div class="driver-avatar-init" style="background:{{ $dBg }};color:#fff;{{ $dPhotoUrl ? 'display:none' : '' }}">{{ $dInitials }}</div>
                            <div class="driver-info">
                                <span class="driver-name">{{ $dName }}</span>
                                <span class="driver-phone">{{ $driver?->phone ?? '—' }}</span>
                            </div>
                        </div>
                    </td>

                    {{-- Départ --}}
                    <td>
                        <div style="font-weight:600;color:#374151;font-size:12px">
                            {{ $trip->departure_time?->format('d/m/Y') ?? '—' }}
                        </div>
                        <div style="font-size:11px;color:#9CA3AF">
                            {{ $trip->departure_time?->format('H:i') ?? '' }}
                        </div>
                    </td>

                    {{-- Statut --}}
                    <td>
                        <span class="badge" style="background:{{ $stData['bg'] }};color:{{ $stData['color'] }}">
                            {{ $stData['label'] }}
                        </span>
                    </td>

                    {{-- Places --}}
                    <td>
                        <div style="font-size:12px;font-weight:600;color:#374151">
                            {{ $trip->available_seats }} / {{ $trip->total_seats }}
                        </div>
                        <div class="seats-bar">
                            <div class="seats-fill {{ $seatClass }}" style="width:{{ $seatsPct }}%"></div>
                        </div>
                    </td>

                    {{-- Prix --}}
                    <td style="font-weight:700;color:#1A5FB4">
                        {{ number_format($trip->price_per_seat) }} F
                    </td>

                    {{-- Réservations --}}
                    <td>
                        <span style="background:#EFF6FF;color:#1A5FB4;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600">
                            {{ $bookCount }}
                        </span>
                    </td>

                    {{-- Actions --}}
                    <td>
                        <div class="action-group">
                            <button class="btn-action" title="Voir le détail"
                                    wire:click="viewTrip({{ $trip->id }})">👁</button>
                            <button class="btn-action warn" title="{{ $trip->is_flagged ? 'Retirer le signalement' : 'Signaler' }}"
                                    wire:click="toggleFlag({{ $trip->id }})"
                                    wire:confirm="{{ $trip->is_flagged ? 'Retirer le signalement de ce trajet ?' : 'Signaler ce trajet ?' }}">
                                {{ $trip->is_flagged ? '🏳️' : '🚩' }}
                            </button>
                            @if(!in_array($trip->status, ['cancelled','completed']))
                            <button class="btn-action danger" title="Annuler le trajet"
                                    wire:click="cancelTrip({{ $trip->id }})"
                                    wire:confirm="Annuler définitivement ce trajet ? Les passagers seront affectés.">✕</button>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        {{-- Pagination --}}
        <div style="padding:12px 20px;border-top:1px solid #F3F4F6">
            {{ $trips->links() }}
        </div>
        @endif
    </div>

</div>

{{-- ── Slide-over panel ───────────────────────────────────────────────────── --}}
@if($selectedTrip)
@php
    $t   = $selectedTrip;
    $st  = $t->status ?? 'pending';
    $stD = $statusLabels[$st] ?? ['label'=>$st,'color'=>'#6B7280','bg'=>'#F3F4F6'];

    $drv      = $t->user;
    $dPrf     = $drv?->profile;
    $drvName  = trim(($dPrf?->first_name ?? '') . ' ' . ($dPrf?->last_name ?? '')) ?: 'Inconnu';
    $drvPhone = $drv?->phone ?? '—';
    $drvPhoto = $dPrf?->profile_photo;
    $drvPhotoUrl = $drvPhoto ? (str_starts_with($drvPhoto,'http') ? $drvPhoto : \Illuminate\Support\Facades\Storage::disk('public')->url($drvPhoto)) : null;
    $drvInit  = strtoupper(substr($dPrf?->first_name ?? 'U', 0, 1) . substr($dPrf?->last_name ?? '', 0, 1));
    $colors   = ['#1A5FB4','#10B981','#F59E0B','#6366F1','#EF4444','#EC4899'];
    $drvBg    = $colors[abs(crc32($drvName)) % count($colors)];

    $veh = $t->vehicle;
    $totalRevenue = $t->bookings->where('payment_status', 'escrow_locked')->sum('total_price');
    $commission   = $t->bookings->where('payment_status', 'escrow_locked')->sum(fn($b) => ($b->total_price ?? 0) * ($t->commission_rate / 100));
@endphp

<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    {{-- Head --}}
    <div class="panel-head">
        <h2>Détail du trajet</h2>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>

    {{-- Body --}}
    <div class="panel-body">

        {{-- Route hero --}}
        <div class="route-hero">
            <div class="route-hero-main">
                {{ $t->departure_city }}
                <span class="route-hero-arrow">→</span>
                {{ $t->arrival_city }}
            </div>
            <div class="route-hero-meta">
                <span>📅 {{ $t->departure_time?->format('d/m/Y à H:i') ?? '—' }}</span>
                @if($t->distance_km)<span>📏 {{ number_format($t->distance_km, 1) }} km</span>@endif
                @if($t->estimated_duration_minutes)<span>⏱ {{ $t->estimated_duration_minutes }} min</span>@endif
                <span class="badge" style="background:rgba(255,255,255,.2);color:#fff;font-size:11px">
                    {{ $stD['label'] }}
                </span>
                @if($t->is_flagged)
                <span style="background:#FEF3C7;color:#D97706;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600">🚩 Signalé</span>
                @endif
            </div>
        </div>

        {{-- Conducteur --}}
        <div class="panel-section">
            <div class="panel-section__title">Conducteur</div>
            <div class="panel-card" style="display:flex;align-items:center;gap:14px">
                @if($drvPhotoUrl)
                    <img src="{{ $drvPhotoUrl }}" alt="{{ $drvInit }}"
                         style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #E5E7EB"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                @endif
                <div style="width:48px;height:48px;border-radius:50%;background:{{ $drvBg }};color:#fff;display:{{ $drvPhotoUrl ? 'none' : 'flex' }};align-items:center;justify-content:center;font-size:16px;font-weight:700;flex-shrink:0">{{ $drvInit }}</div>
                <div>
                    <div style="font-size:15px;font-weight:700;color:#111827">{{ $drvName }}</div>
                    <div style="font-size:12px;color:#6B7280">{{ $drvPhone }}</div>
                    @if($dPrf?->kyc_status)
                    <span class="badge" style="background:{{ $dPrf->kyc_status === 'approved' ? '#D1FAE5' : '#FEF3C7' }};color:{{ $dPrf->kyc_status === 'approved' ? '#065F46' : '#92400E' }};font-size:10px;margin-top:4px">
                        KYC {{ $dPrf->kyc_status === 'approved' ? 'Vérifié ✓' : ucfirst($dPrf->kyc_status) }}
                    </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Véhicule --}}
        @if($veh)
        <div class="panel-section">
            <div class="panel-section__title">Véhicule</div>
            <div class="panel-card">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Marque / Modèle</label>
                        <span>{{ $veh->brand }} {{ $veh->model }}</span>
                    </div>
                    <div class="info-item">
                        <label>Immatriculation</label>
                        <span style="font-family:monospace;letter-spacing:1px">{{ $veh->plate_number ?? '—' }}</span>
                    </div>
                    <div class="info-item">
                        <label>Couleur</label>
                        <span>{{ $veh->color ?? '—' }}</span>
                    </div>
                    <div class="info-item">
                        <label>Places totales</label>
                        <span>{{ $veh->seats ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Détails du trajet --}}
        <div class="panel-section">
            <div class="panel-section__title">Détails du trajet</div>
            <div class="panel-card">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Prix / place</label>
                        <span style="color:#1A5FB4;font-weight:700">{{ number_format($t->price_per_seat) }} FCFA</span>
                    </div>
                    <div class="info-item">
                        <label>Mode réservation</label>
                        <span>{{ $t->booking_mode === 'instant' ? '⚡ Instantané' : '✋ Approbation' }}</span>
                    </div>
                    <div class="info-item">
                        <label>Places total</label>
                        <span>{{ $t->total_seats }}</span>
                    </div>
                    <div class="info-item">
                        <label>Places disponibles</label>
                        <span>{{ $t->available_seats }}</span>
                    </div>
                    <div class="info-item">
                        <label>Commission</label>
                        <span>{{ $t->commission_rate ?? 0 }}%</span>
                    </div>
                    <div class="info-item">
                        <label>Récurrent</label>
                        <span>{{ $t->is_recurring ? 'Oui' : 'Non' }}</span>
                    </div>
                </div>
                @if($t->description)
                <div style="margin-top:10px;font-size:12px;color:#6B7280;border-top:1px solid #E5E7EB;padding-top:10px">
                    {{ $t->description }}
                </div>
                @endif
            </div>
        </div>

        {{-- Finances --}}
        <div class="panel-section">
            <div class="panel-section__title">Finances</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div style="background:#EFF6FF;border-radius:10px;padding:14px 16px">
                    <div style="font-size:10px;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Revenus totaux</div>
                    <div style="font-size:18px;font-weight:700;color:#1A5FB4">{{ number_format($totalRevenue) }} F</div>
                </div>
                <div style="background:#F0FDF4;border-radius:10px;padding:14px 16px">
                    <div style="font-size:10px;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Commission plateforme</div>
                    <div style="font-size:18px;font-weight:700;color:#10B981">{{ number_format($commission) }} F</div>
                </div>
            </div>
        </div>

        {{-- Réservations --}}
        @if($t->bookings->isNotEmpty())
        <div class="panel-section">
            <div class="panel-section__title">Réservations ({{ $t->bookings->count() }})</div>
            @foreach($t->bookings->take(10) as $bk)
            @php
                $pax        = $bk->passenger;
                $paxPrf     = $pax?->profile;
                $paxName    = trim(($paxPrf?->first_name ?? '') . ' ' . ($paxPrf?->last_name ?? '')) ?: 'Passager #' . $bk->id;
                $paxInit    = strtoupper(substr($paxPrf?->first_name ?? 'P', 0, 1) . substr($paxPrf?->last_name ?? '', 0, 1));
                $paxBg      = $colors[abs(crc32($paxName)) % count($colors)];
                $bkStData   = $statusLabels[$bk->status ?? 'pending'] ?? ['label'=>$bk->status,'color'=>'#6B7280','bg'=>'#F3F4F6'];
                $payStData  = $payLabels[$bk->payment_status ?? 'pending'] ?? ['label'=>$bk->payment_status,'color'=>'#6B7280','bg'=>'#F3F4F6'];
            @endphp
            <div class="booking-item">
                <div class="booking-pax-avatar" style="background:{{ $paxBg }};color:#fff">{{ $paxInit }}</div>
                <div class="booking-pax-info">
                    <div class="booking-pax-name">{{ $paxName }}</div>
                    <div class="booking-pax-detail">
                        {{ $bk->seats_booked }} place(s) ·
                        <span class="badge" style="background:{{ $bkStData['bg'] }};color:{{ $bkStData['color'] }};font-size:10px">{{ $bkStData['label'] }}</span>
                        <span class="badge" style="background:{{ $payStData['bg'] }};color:{{ $payStData['color'] }};font-size:10px;margin-left:4px">{{ $payStData['label'] }}</span>
                    </div>
                </div>
                <div class="booking-amount">{{ number_format($bk->total_price ?? 0) }} F</div>
            </div>
            @endforeach
            @if($t->bookings->count() > 10)
            <div style="font-size:11px;color:#9CA3AF;text-align:center;margin-top:8px">
                + {{ $t->bookings->count() - 10 }} autres réservations
            </div>
            @endif
        </div>
        @endif

        {{-- Incidents --}}
        @if($t->incidents && $t->incidents->isNotEmpty())
        <div class="panel-section">
            <div class="panel-section__title">Incidents ({{ $t->incidents->count() }})</div>
            @foreach($t->incidents as $inc)
            <div style="background:#FEF3C7;border-radius:8px;padding:10px 12px;margin-bottom:8px;font-size:12px">
                <div style="font-weight:600;color:#92400E">⚠️ {{ $inc->type ?? 'Incident' }}</div>
                @if($inc->description)
                <div style="color:#78350F;margin-top:4px">{{ $inc->description }}</div>
                @endif
                <div style="color:#92400E;margin-top:4px;font-size:11px">
                    {{ $inc->created_at?->format('d/m/Y H:i') }}
                    @if($inc->resolved_at) · Résolu le {{ $inc->resolved_at->format('d/m/Y') }} @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif

    </div>

    {{-- Panel footer actions --}}
    <div class="panel-actions">
        @if($t->is_flagged)
        <button class="btn-panel btn-panel-warn" wire:click="toggleFlag({{ $t->id }})" wire:confirm="Retirer le signalement ?">
            🏳️ Désignaler
        </button>
        @else
        <button class="btn-panel btn-panel-warn" wire:click="toggleFlag({{ $t->id }})" wire:confirm="Signaler ce trajet ?">
            🚩 Signaler
        </button>
        @endif
        @if(!in_array($t->status, ['cancelled','completed']))
        <button class="btn-panel btn-panel-danger" wire:click="cancelTrip({{ $t->id }})" wire:confirm="Annuler ce trajet définitivement ?">
            Annuler le trajet
        </button>
        @endif
        <button class="btn-panel btn-panel-primary" wire:click="closeView">Fermer</button>
    </div>
</div>
@endif
