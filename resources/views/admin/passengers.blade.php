@php
$kycLabels = [
    'approved'  => ['label' => 'Vérifié',  'color' => '#10B981', 'bg' => '#D1FAE5', 'icon' => '✅'],
    'pending'   => ['label' => 'En attente','color' => '#F59E0B', 'bg' => '#FEF3C7', 'icon' => '⏳'],
    'rejected'  => ['label' => 'Rejeté',   'color' => '#EF4444', 'bg' => '#FEE2E2', 'icon' => '❌'],
    'none'      => ['label' => 'Aucun',    'color' => '#9CA3AF', 'bg' => '#F3F4F6', 'icon' => '○'],
];
@endphp

<div>
<style>
.pax-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.pax-header{margin-bottom:24px}
.pax-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.pax-header p{font-size:13px;color:#6B7280;margin:0}

.stat-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:#fff;border-radius:12px;padding:16px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);cursor:pointer;transition:box-shadow .15s,transform .15s;border:2px solid transparent}
.stat-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.1);transform:translateY(-1px)}
.stat-card.active{border-color:#1A5FB4}
.stat-card.no-click{cursor:default}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-value{font-size:20px;font-weight:700;color:#111827;line-height:1.2}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}

.filter-bar{background:#fff;border-radius:12px;padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
.filter-input{flex:1;min-width:220px;padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;transition:border-color .15s}
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
.data-table tr.blocked-row td{background:#FFF5F5}

.user-cell{display:flex;align-items:center;gap:10px}
.avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600}

.rating-stars{display:flex;gap:2px;align-items:center}
.star{font-size:12px}

.action-group{display:flex;gap:6px}
.btn-action{width:30px;height:30px;border-radius:7px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s}
.btn-action:hover{border-color:#1A5FB4;background:#EFF6FF}
.btn-danger{border-color:#FECACA}
.btn-danger:hover{border-color:#EF4444;background:#FEE2E2}
.btn-success{border-color:#A7F3D0}
.btn-success:hover{border-color:#10B981;background:#D1FAE5}

.empty-state{padding:60px;text-align:center;color:#9CA3AF}
.empty-state .icon{font-size:40px;margin-bottom:12px}

/* Panel */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:500px;max-width:95vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-head h2{font-size:16px;font-weight:700;color:#111827;margin:0}
.panel-close{width:32px;height:32px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:#6B7280;transition:all .15s}
.panel-close:hover{background:#FEE2E2;border-color:#EF4444;color:#EF4444}
.panel-body{flex:1;overflow-y:auto;padding:24px}
.panel-footer{padding:16px 24px;border-top:1px solid #F3F4F6;flex-shrink:0;display:flex;gap:8px}

.panel-section{margin-bottom:20px}
.panel-section__title{font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px}
.panel-card{background:#F9FAFB;border-radius:10px;padding:14px 16px}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #F3F4F6}
.info-row:last-child{border-bottom:none}
.info-row label{font-size:12px;color:#6B7280}
.info-row span{font-size:13px;font-weight:500;color:#374151;text-align:right}

.hero-card{background:linear-gradient(135deg,#1A5FB4,#2563EB);border-radius:12px;padding:20px;margin-bottom:20px;color:#fff;display:flex;align-items:center;gap:16px}
.hero-avatar{width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;flex-shrink:0}
.hero-name{font-size:18px;font-weight:700;margin-bottom:4px}
.hero-sub{font-size:13px;opacity:.85}

.mini-stats{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:20px}
.mini-stat{background:#F0F7FF;border-radius:10px;padding:12px;text-align:center}
.mini-stat-val{font-size:18px;font-weight:700;color:#1A5FB4}
.mini-stat-lbl{font-size:10px;color:#6B7280;text-transform:uppercase;margin-top:2px}

.review-item{padding:10px 0;border-bottom:1px solid #F3F4F6}
.review-item:last-child{border-bottom:none}
.review-meta{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px}
.review-text{font-size:12px;color:#6B7280;line-height:1.4}

.btn-block{flex:1;padding:10px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:.15s}
.btn-block.block{background:#FEE2E2;color:#EF4444}
.btn-block.block:hover{background:#EF4444;color:#fff}
.btn-block.unblock{background:#D1FAE5;color:#10B981}
.btn-block.unblock:hover{background:#10B981;color:#fff}
.btn-close-panel{flex:1;padding:10px;background:#F3F4F6;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#6B7280;cursor:pointer}
.btn-close-panel:hover{background:#E5E7EB}
</style>

<div class="pax-wrap">

    <div class="pax-header">
        <h1>Passagers</h1>
        <p>Gestion des comptes passagers de la plateforme</p>
    </div>

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card {{ $kycFilter===''&&$blockFilter==='' ? 'active' : '' }}"
             wire:click="$set('kycFilter',''); $set('blockFilter','')">
            <div class="stat-icon" style="background:#EFF6FF">👤</div>
            <div><div class="stat-value">{{ number_format($stats['total']) }}</div><div class="stat-label">Total</div></div>
        </div>
        <div class="stat-card {{ $kycFilter==='approved' ? 'active' : '' }}"
             wire:click="$set('kycFilter','approved')">
            <div class="stat-icon" style="background:#D1FAE5">✅</div>
            <div><div class="stat-value" style="color:#10B981">{{ number_format($stats['verified']) }}</div><div class="stat-label">Vérifiés</div></div>
        </div>
        <div class="stat-card {{ $kycFilter==='pending' ? 'active' : '' }}"
             wire:click="$set('kycFilter','pending')">
            <div class="stat-icon" style="background:#FEF3C7">⏳</div>
            <div><div class="stat-value" style="{{ $stats['pending']>0?'color:#D97706':'' }}">{{ number_format($stats['pending']) }}</div><div class="stat-label">KYC en attente</div></div>
        </div>
        <div class="stat-card {{ $blockFilter==='1' ? 'active' : '' }}"
             wire:click="$set('blockFilter','1')">
            <div class="stat-icon" style="background:#FEE2E2">🚫</div>
            <div><div class="stat-value" style="{{ $stats['blocked']>0?'color:#EF4444':'' }}">{{ number_format($stats['blocked']) }}</div><div class="stat-label">Bloqués</div></div>
        </div>
        <div class="stat-card no-click">
            <div class="stat-icon" style="background:#EDE9FE">🎫</div>
            <div><div class="stat-value" style="color:#6366F1">{{ number_format($stats['bookings']) }}</div><div class="stat-label">Réservations</div></div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-bar">
        <input type="text" class="filter-input" placeholder="🔍  Nom, téléphone, email, ville…"
               wire:model.live.debounce.300ms="search">
        <select class="filter-select" wire:model.live="kycFilter">
            <option value="">Tout KYC</option>
            <option value="approved">Vérifié</option>
            <option value="pending">En attente</option>
            <option value="rejected">Rejeté</option>
        </select>
        <select class="filter-select" wire:model.live="blockFilter">
            <option value="">Tout statut</option>
            <option value="0">Actif</option>
            <option value="1">Bloqué</option>
        </select>
        <input type="date" class="filter-date" wire:model.live="dateFrom">
        <input type="date" class="filter-date" wire:model.live="dateTo">
        <button class="btn-reset" wire:click="resetFilters">✕ Réinitialiser</button>
    </div>

    {{-- Table --}}
    <div class="table-wrap">
        <div class="table-head">
            <h2>Passagers</h2>
            <span class="table-count">{{ $passengers->total() }} passager(s)</span>
        </div>

        @if($passengers->isEmpty())
        <div class="empty-state"><div class="icon">👤</div><p>Aucun passager trouvé</p></div>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Passager</th>
                    <th>Téléphone</th>
                    <th>Ville</th>
                    <th>KYC</th>
                    <th>Réservations</th>
                    <th>Note</th>
                    <th>Pénalités</th>
                    <th>Inscrit le</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($passengers as $pax)
                @php
                    $pr     = $pax->profile;
                    $name   = trim(($pr?->first_name??'').(' '.($pr?->last_name??''))) ?: $pax->phone;
                    $init   = strtoupper(substr($pr?->first_name??'P',0,1).substr($pr?->last_name??'',0,1))?:'P';
                    $pal    = ['#1A5FB4','#10B981','#F59E0B','#6366F1','#EF4444','#EC4899','#8B5CF6'];
                    $bg     = $pal[abs(crc32($name))%count($pal)];
                    $kyc    = $kycLabels[$pr?->kyc_status??'none'] ?? $kycLabels['none'];
                    $bkCnt  = $pax->bookings_count ?? $pax->bookings->count();
                    $rating = $pax->averageRating();
                @endphp
                <tr class="{{ $pax->is_blocked ? 'blocked-row' : '' }}">
                    <td>
                        <div class="user-cell">
                            <div class="avatar" style="background:{{ $bg }};color:#fff">{{ $init }}</div>
                            <div>
                                <div style="font-weight:600;font-size:13px;color:#111827">{{ $name }}</div>
                                @if($pr?->email)<div style="font-size:11px;color:#9CA3AF">{{ $pr->email }}</div>@endif
                            </div>
                            @if($pax->is_blocked)
                            <span style="font-size:10px;background:#FEE2E2;color:#EF4444;padding:2px 7px;border-radius:10px;font-weight:700;margin-left:4px">BLOQUÉ</span>
                            @endif
                        </div>
                    </td>
                    <td style="font-family:monospace;font-size:12px">{{ $pax->phone }}</td>
                    <td style="font-size:12px">{{ $pr?->city??'—' }}</td>
                    <td>
                        <span class="badge" style="background:{{ $kyc['bg'] }};color:{{ $kyc['color'] }}">
                            {{ $kyc['icon'] }} {{ $kyc['label'] }}
                        </span>
                    </td>
                    <td style="font-weight:600;text-align:center">{{ $bkCnt }}</td>
                    <td>
                        @if($rating)
                        <span style="font-size:13px;font-weight:700;color:#F59E0B">★ {{ $rating }}</span>
                        @else
                        <span style="color:#D1D5DB">—</span>
                        @endif
                    </td>
                    <td>
                        @if($pax->penalty_points > 0)
                        <span style="background:#FEE2E2;color:#EF4444;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700">{{ $pax->penalty_points }} pts</span>
                        @else
                        <span style="color:#D1D5DB">0</span>
                        @endif
                    </td>
                    <td style="font-size:11px;color:#6B7280">{{ $pax->created_at?->format('d/m/Y') }}</td>
                    <td>
                        <div class="action-group">
                            <button class="btn-action" wire:click="view({{ $pax->id }})" title="Voir le profil">👁</button>
                            <button class="btn-action {{ $pax->is_blocked ? 'btn-success' : 'btn-danger' }}"
                                    wire:click="toggleBlock({{ $pax->id }})"
                                    wire:confirm="{{ $pax->is_blocked ? 'Débloquer ce passager ?' : 'Bloquer ce passager ?' }}"
                                    title="{{ $pax->is_blocked ? 'Débloquer' : 'Bloquer' }}">
                                {{ $pax->is_blocked ? '🔓' : '🔒' }}
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="padding:12px 20px;border-top:1px solid #F3F4F6">{{ $passengers->links() }}</div>
        @endif
    </div>

</div>

{{-- Panel --}}
@if($selected)
@php
    $s    = $selected;
    $pr2  = $s->profile;
    $nm2  = trim(($pr2?->first_name??'').(' '.($pr2?->last_name??''))) ?: $s->phone;
    $in2  = strtoupper(substr($pr2?->first_name??'P',0,1).substr($pr2?->last_name??'',0,1))?:'P';
    $pal2 = ['#1A5FB4','#10B981','#F59E0B','#6366F1','#EF4444','#EC4899','#8B5CF6'];
    $bg2  = $pal2[abs(crc32($nm2))%count($pal2)];
    $kyc2 = $kycLabels[$pr2?->kyc_status??'none'] ?? $kycLabels['none'];
    $bks  = $s->bookings ?? collect();
    $tot  = $bks->count();
    $done = $bks->where('status','accepted')->count();
    $spent= $bks->where('payment_status','escrow_locked')->sum('total_price');
    $rat2 = $s->averageRating();
    $revs = $s->reviewsReceived ?? collect();
@endphp
<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    <div class="panel-head">
        <h2>Profil passager</h2>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>
    <div class="panel-body">

        {{-- Hero --}}
        <div class="hero-card">
            <div class="hero-avatar" style="background:{{ $bg2 }}20;border:2px solid rgba(255,255,255,.4)">
                <span style="color:#fff;font-size:22px;font-weight:700">{{ $in2 }}</span>
            </div>
            <div>
                <div class="hero-name">{{ $nm2 }}</div>
                <div class="hero-sub">{{ $s->phone }}</div>
                <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
                    <span style="background:rgba(255,255,255,.2);padding:3px 10px;border-radius:12px;font-size:11px">
                        {{ $kyc2['icon'] }} {{ $kyc2['label'] }}
                    </span>
                    @if($s->is_blocked)
                    <span style="background:#EF444440;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700">🚫 BLOQUÉ</span>
                    @else
                    <span style="background:rgba(255,255,255,.2);padding:3px 10px;border-radius:12px;font-size:11px">✅ Actif</span>
                    @endif
                    @if($rat2)
                    <span style="background:rgba(255,255,255,.2);padding:3px 10px;border-radius:12px;font-size:11px">★ {{ $rat2 }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Mini stats --}}
        <div class="mini-stats">
            <div class="mini-stat">
                <div class="mini-stat-val">{{ $tot }}</div>
                <div class="mini-stat-lbl">Réservations</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-val">{{ $done }}</div>
                <div class="mini-stat-lbl">Acceptées</div>
            </div>
            <div class="mini-stat" style="background:#F0FDF4">
                <div class="mini-stat-val" style="color:#10B981">{{ number_format($spent) }}</div>
                <div class="mini-stat-lbl">F dépensés</div>
            </div>
        </div>

        {{-- Infos perso --}}
        <div class="panel-section">
            <div class="panel-section__title">Informations personnelles</div>
            <div class="panel-card">
                <div class="info-row"><label>Prénom</label><span>{{ $pr2?->first_name??'—' }}</span></div>
                <div class="info-row"><label>Nom</label><span>{{ $pr2?->last_name??'—' }}</span></div>
                <div class="info-row"><label>Genre</label><span>{{ $pr2?->gender??'—' }}</span></div>
                <div class="info-row"><label>Email</label><span>{{ $pr2?->email??'—' }}</span></div>
                <div class="info-row"><label>Ville</label><span>{{ $pr2?->city??'—' }}</span></div>
                <div class="info-row"><label>Quartier</label><span>{{ $pr2?->neighborhood??'—' }}</span></div>
                <div class="info-row"><label>Inscrit le</label><span>{{ $s->created_at?->format('d/m/Y H:i') }}</span></div>
                <div class="info-row"><label>Tél. vérifié</label><span>{{ $s->phone_verified_at?->format('d/m/Y')?? 'Non' }}</span></div>
            </div>
        </div>

        {{-- Pénalités --}}
        @if($s->penalty_points > 0)
        <div class="panel-section">
            <div class="panel-section__title">Pénalités</div>
            <div class="panel-card" style="background:#FFF5F5">
                <div class="info-row">
                    <label>Points de pénalité</label>
                    <span style="font-weight:700;color:#EF4444">{{ $s->penalty_points }}</span>
                </div>
                @foreach($s->penalties??[] as $pen)
                <div class="info-row">
                    <label>{{ $pen->created_at?->format('d/m/Y') }}</label>
                    <span>{{ $pen->reason??'—' }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Contacts urgence --}}
        @if($s->emergencyContacts && $s->emergencyContacts->count())
        <div class="panel-section">
            <div class="panel-section__title">Contacts d'urgence</div>
            <div class="panel-card">
                @foreach($s->emergencyContacts as $ec)
                <div class="info-row">
                    <label>{{ $ec->name }} ({{ $ec->relationship??'—' }})</label>
                    <span>{{ $ec->phone }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Dernières réservations --}}
        @if($bks->count())
        <div class="panel-section">
            <div class="panel-section__title">Dernières réservations</div>
            <div class="panel-card">
                @foreach($bks->take(5) as $bk)
                @php
                    $tr3 = $bk->trip;
                    $stC = ['pending'=>'#F59E0B','accepted'=>'#10B981','rejected'=>'#EF4444','cancelled'=>'#9CA3AF'][$bk->status??'pending']??'#9CA3AF';
                @endphp
                <div class="info-row">
                    <label>{{ $tr3?->departure_city??'?' }} → {{ $tr3?->arrival_city??'?' }}</label>
                    <span>
                        <span style="color:{{ $stC }};font-weight:600;font-size:11px">{{ $bk->status }}</span>
                        &nbsp;{{ number_format($bk->total_price??0) }} F
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Avis reçus --}}
        @if($revs->count())
        <div class="panel-section">
            <div class="panel-section__title">Avis reçus</div>
            <div class="panel-card">
                @foreach($revs as $rev)
                <div class="review-item">
                    <div class="review-meta">
                        <span class="rating-stars">
                            @for($i=1;$i<=5;$i++)
                            <span class="star" style="color:{{ $i<=$rev->rating?'#F59E0B':'#E5E7EB' }}">★</span>
                            @endfor
                        </span>
                        <span style="font-size:11px;color:#9CA3AF">{{ $rev->created_at?->format('d/m') }}</span>
                    </div>
                    @if($rev->comment)
                    <div class="review-text">{{ $rev->comment }}</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
    <div class="panel-footer">
        <button class="btn-block {{ $s->is_blocked ? 'unblock' : 'block' }}"
                wire:click="toggleBlock({{ $s->id }})"
                wire:confirm="{{ $s->is_blocked ? 'Débloquer ce passager ?' : 'Bloquer ce passager ?' }}">
            {{ $s->is_blocked ? '🔓 Débloquer' : '🔒 Bloquer' }}
        </button>
        <button class="btn-close-panel" wire:click="closeView">Fermer</button>
    </div>
</div>
@endif
</div>
