<div>

@once
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endonce

<style>
.track-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.track-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.track-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.track-header p{font-size:13px;color:#6B7280;margin:0}
.live-badge{display:flex;align-items:center;gap:6px;background:#D1FAE5;border:1.5px solid #6EE7B7;border-radius:20px;padding:6px 14px;font-size:12px;font-weight:700;color:#065F46;flex-shrink:0}
.live-dot{width:8px;height:8px;border-radius:50%;background:#10B981;animation:pulse 1.5s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

.stat-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px}
@media(max-width:1100px){.stat-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:700px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
.stat-card{background:#fff;border-radius:12px;padding:14px 16px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;gap:12px;cursor:pointer;border:2px solid transparent;transition:all .15s}
.stat-card:hover{box-shadow:0 3px 10px rgba(0,0,0,.1)}
.stat-card.active-filter{border-color:#1A5FB4}
.stat-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.stat-value{font-size:20px;font-weight:700;color:#111827}
.stat-label{font-size:10px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px}

.filter-bar{background:#fff;border-radius:12px;padding:12px 18px;display:flex;gap:10px;align-items:center;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:18px;flex-wrap:wrap}
.filter-select{padding:7px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;background:#fff;cursor:pointer}
.filter-select:focus{border-color:#1A5FB4}
.filter-label{font-size:12px;color:#9CA3AF;font-weight:600;white-space:nowrap}

.status-tabs{display:flex;gap:6px;flex-wrap:wrap}
.status-tab{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;transition:all .15s;white-space:nowrap;font-family:inherit}
.status-tab:hover{border-color:#1A5FB4;color:#1A5FB4}
.status-tab.tab-all.active{background:#1A5FB4;color:#fff;border-color:#1A5FB4}
.status-tab.tab-active.active{background:#D1FAE5;color:#065F46;border-color:#6EE7B7}
.status-tab.tab-pending.active{background:#FEF3C7;color:#92400E;border-color:#FCD34D}
.status-tab.tab-completed.active{background:#F3F4F6;color:#374151;border-color:#D1D5DB}

/* Map */
.map-container{background:#e9ecef;border-radius:16px;height:480px;margin-bottom:20px;position:relative;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.12)}
#minizon-map{height:100%;width:100%}
.map-legend{position:absolute;bottom:12px;left:12px;z-index:1000;background:rgba(255,255,255,.95);border-radius:10px;padding:10px 14px;box-shadow:0 2px 8px rgba(0,0,0,.15);display:flex;gap:14px;flex-wrap:wrap}
.map-legend-item{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:600;color:#374151}
.legend-dot{width:10px;height:10px;border-radius:50%}
.map-counter{position:absolute;top:12px;right:12px;z-index:1000;background:rgba(255,255,255,.95);border-radius:10px;padding:8px 14px;box-shadow:0 2px 8px rgba(0,0,0,.15);font-size:12px;color:#374151;font-weight:600}

/* Trip list */
.section-title{font-size:13px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px}
.trip-grid{display:flex;flex-direction:column;gap:8px;margin-bottom:24px}
.trip-item{background:#fff;border-radius:12px;padding:14px 18px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;gap:14px;cursor:pointer;border:2px solid transparent;transition:all .15s}
.trip-item:hover{border-color:#BFDBFE;box-shadow:0 3px 10px rgba(0,0,0,.08)}
.trip-item.selected{border-color:#1A5FB4;background:#F0F7FF}
.trip-item.no-gps{border-style:dashed}
.trip-item.has-incident{border-color:#FEE2E2;background:#FFFAFA}

.status-pill{padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;white-space:nowrap}
.pill-active{background:#D1FAE5;color:#065F46}
.pill-pending{background:#FEF3C7;color:#92400E}
.pill-completed{background:#F3F4F6;color:#374151}
.pill-incident{background:#FEE2E2;color:#DC2626}

.driver-av{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0}
.trip-content{flex:1;min-width:0}
.trip-route{font-size:13px;font-weight:700;color:#111827}
.trip-driver{font-size:12px;color:#6B7280;margin-top:2px}
.trip-meta{font-size:11px;color:#9CA3AF;margin-top:3px;display:flex;gap:8px;flex-wrap:wrap}
.trip-right{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0}
.speed-badge{padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;display:flex;align-items:center;gap:3px}
.gps-ok{background:#D1FAE5;color:#065F46}
.gps-missing{background:#F3F4F6;color:#9CA3AF}

/* Panel */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:480px;max-width:95vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:18px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-head h2{font-size:16px;font-weight:700;color:#111827;margin:0}
.panel-close{width:32px;height:32px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:#6B7280;transition:all .15s}
.panel-close:hover{background:#FEE2E2;border-color:#EF4444;color:#EF4444}
.panel-body{flex:1;overflow-y:auto;padding:24px}
.panel-section{margin-bottom:20px}
.panel-section__title{font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px}
.panel-card{background:#F9FAFB;border-radius:10px;padding:14px 16px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.info-item label{font-size:10px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:2px}
.info-item span{font-size:13px;font-weight:600;color:#374151}
.gps-coords-box{background:linear-gradient(135deg,#0F172A,#1E293B);border-radius:12px;padding:16px;text-align:center;margin-bottom:10px}
.gps-coords{font-family:monospace;font-size:13px;color:#6EE7B7;font-weight:700}
.locate-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1A5FB4;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:background .15s;width:100%;justify-content:center}
.locate-btn:hover{background:#0F4A9E}

.empty-state{background:#fff;border-radius:12px;padding:48px;text-align:center;color:#9CA3AF;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.empty-state .icon{font-size:36px;margin-bottom:10px}

/* Leaflet popup */
.leaflet-popup-content{margin:10px 14px;font-family:'Inter',system-ui,sans-serif}
.leaflet-popup-content-wrapper{border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.15)}
</style>

{{-- Polling silencieux toutes les 5s --}}
<div wire:poll.5000ms="refreshPositions" style="display:none" aria-hidden="true"></div>

<div class="track-wrap">

    {{-- Header --}}
    <div class="track-header">
        <div>
            <h1>Suivi Temps Réel</h1>
            <p>Tous les trajets actifs, en attente et terminés sur la plateforme</p>
        </div>
        <div class="live-badge">
            <div class="live-dot"></div>
            EN DIRECT
        </div>
    </div>

    {{-- Stats cliquables (filtre rapide) --}}
    <div class="stat-grid">
        <div class="stat-card {{ $statusFilter === '' ? 'active-filter' : '' }}" wire:click="$set('statusFilter','')">
            <div class="stat-icon" style="background:#EFF6FF">🗺️</div>
            <div>
                <div class="stat-value">{{ number_format($stats['active'] + $stats['pending'] + $stats['completed']) }}</div>
                <div class="stat-label">Tous les trajets</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter === 'active' ? 'active-filter' : '' }}" wire:click="$set('statusFilter','active')">
            <div class="stat-icon" style="background:#D1FAE5">🚗</div>
            <div>
                <div class="stat-value" style="color:#10B981">{{ number_format($stats['active']) }}</div>
                <div class="stat-label">En cours</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter === 'pending' ? 'active-filter' : '' }}" wire:click="$set('statusFilter','pending')">
            <div class="stat-icon" style="background:#FEF3C7">⏳</div>
            <div>
                <div class="stat-value" style="color:#D97706">{{ number_format($stats['pending']) }}</div>
                <div class="stat-label">En attente</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter === 'completed' ? 'active-filter' : '' }}" wire:click="$set('statusFilter','completed')">
            <div class="stat-icon" style="background:#F3F4F6">✅</div>
            <div>
                <div class="stat-value" style="color:#6B7280">{{ number_format($stats['completed']) }}</div>
                <div class="stat-label">Terminés</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#EDE9FE">⚡</div>
            <div>
                <div class="stat-value">{{ $stats['avg_speed'] > 0 ? number_format($stats['avg_speed'], 0).' km/h' : '—' }}</div>
                <div class="stat-label">Vitesse moy.</div>
            </div>
        </div>
    </div>

    {{-- Filtres --}}
    <div class="filter-bar">
        <span class="filter-label">Ville :</span>
        <select class="filter-select" wire:model.live="cityFilter">
            <option value="">Toutes</option>
            @foreach($cities as $city)
            <option value="{{ $city }}">{{ $city }}</option>
            @endforeach
        </select>

        <span class="filter-label" style="margin-left:8px">Statut :</span>
        <div class="status-tabs">
            <button class="status-tab tab-all {{ $statusFilter === '' ? 'active' : '' }}" wire:click="$set('statusFilter','')">Tous</button>
            <button class="status-tab tab-active {{ $statusFilter === 'active' ? 'active' : '' }}" wire:click="$set('statusFilter','active')">🚗 En cours</button>
            <button class="status-tab tab-pending {{ $statusFilter === 'pending' ? 'active' : '' }}" wire:click="$set('statusFilter','pending')">⏳ En attente</button>
            <button class="status-tab tab-completed {{ $statusFilter === 'completed' ? 'active' : '' }}" wire:click="$set('statusFilter','completed')">✅ Terminés</button>
        </div>
    </div>

    {{-- CARTE LEAFLET — wire:ignore protège le DOM Leaflet des re-renders Livewire --}}
    <div wire:ignore class="map-container">
        <div id="minizon-map"></div>
        <div class="map-legend">
            <div class="map-legend-item"><div class="legend-dot" style="background:#1A5FB4"></div>En cours (GPS)</div>
            <div class="map-legend-item"><div class="legend-dot" style="background:#F59E0B"></div>En attente</div>
            <div class="map-legend-item"><div class="legend-dot" style="background:#6B7280"></div>Terminé</div>
            <div class="map-legend-item"><div class="legend-dot" style="background:#EF4444"></div>Incident</div>
            <div class="map-legend-item"><div class="legend-dot" style="background:#10B981"></div>Départ 🟢</div>
            <div class="map-legend-item"><div class="legend-dot" style="background:#EF4444;border-radius:2px"></div>Arrivée 🔴</div>
        </div>
        <div class="map-counter" id="map-counter">—</div>
    </div>

    {{-- Liste des trajets --}}
    @if($trips->isEmpty())
    <div class="empty-state">
        <div class="icon">
            @if($statusFilter === 'active') 🚗
            @elseif($statusFilter === 'pending') ⏳
            @elseif($statusFilter === 'completed') ✅
            @else 🗺️
            @endif
        </div>
        <p style="font-size:14px;font-weight:600;color:#374151">
            @if($statusFilter === 'active') Aucun trajet en cours
            @elseif($statusFilter === 'pending') Aucun trajet en attente
            @elseif($statusFilter === 'completed') Aucun trajet terminé aujourd'hui
            @else Aucun trajet trouvé
            @endif
        </p>
        <p style="font-size:12px;margin-top:6px">Les trajets apparaîtront ici dès qu'ils seront créés</p>
    </div>
    @else
    <div class="trip-grid">
        @foreach($trips as $trip)
        @php
            $driver   = $trip->user;
            $dProfile = $driver?->profile;
            $dName    = trim(($dProfile?->first_name??'').(' '.($dProfile?->last_name??''))) ?: 'Conducteur';
            $dInit    = strtoupper(substr($dProfile?->first_name??'C',0,1).substr($dProfile?->last_name??'',0,1));
            $palette  = ['#1A5FB4','#10B981','#F59E0B','#6366F1','#EF4444','#EC4899'];
            $dBg      = $palette[abs(crc32($dName))%count($palette)];
            $hasGps   = $trip->current_latitude && $trip->current_longitude;
            $hasIncident = $trip->activeIncident !== null;
        @endphp
        <div class="trip-item
             {{ $selectedId === $trip->id ? 'selected' : '' }}
             {{ !$hasGps && $trip->status === 'active' ? 'no-gps' : '' }}
             {{ $hasIncident ? 'has-incident' : '' }}"
             wire:click="view({{ $trip->id }})">

            <div class="driver-av" style="background:{{ $dBg }}">{{ $dInit }}</div>

            <div class="trip-content">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span class="trip-route">{{ $trip->departure_city }} <span style="color:#FF7A45">→</span> {{ $trip->arrival_city }}</span>
                    @if($hasIncident)
                        <span class="status-pill pill-incident">⚠️ Incident</span>
                    @elseif($trip->status === 'active')
                        <span class="status-pill pill-active">🚗 En cours</span>
                    @elseif($trip->status === 'pending')
                        <span class="status-pill pill-pending">⏳ En attente</span>
                    @else
                        <span class="status-pill pill-completed">✅ Terminé</span>
                    @endif
                </div>
                <div class="trip-driver">{{ $dName }} · {{ $driver?->phone ?? '—' }}</div>
                <div class="trip-meta">
                    @if($hasGps)
                        <span>📍 {{ number_format($trip->current_latitude, 4) }}, {{ number_format($trip->current_longitude, 4) }}</span>
                    @elseif($trip->departure_latitude)
                        <span>📌 Point de départ connu</span>
                    @else
                        <span>📡 Pas de position GPS</span>
                    @endif
                    @if($trip->status === 'pending' && $trip->departure_time)
                        <span>🕐 Départ prévu {{ $trip->departure_time->format('H:i') }}</span>
                    @elseif($trip->started_at)
                        <span>🕐 Démarré {{ $trip->started_at->diffForHumans() }}</span>
                    @endif
                    @if($trip->bookings_count ?? $trip->bookings?->count())
                        <span>👥 {{ $trip->bookings?->count() ?? 0 }} passager(s)</span>
                    @endif
                </div>
            </div>

            <div class="trip-right">
                @if($hasIncident)
                    <div class="speed-badge" style="background:#FEE2E2;color:#DC2626">⚠️ Incident</div>
                @elseif($hasGps && $trip->current_speed !== null)
                    <div class="speed-badge gps-ok">⚡ {{ number_format($trip->current_speed, 0) }} km/h</div>
                @elseif($trip->status === 'active')
                    <div class="speed-badge gps-ok">📍 GPS actif</div>
                @elseif($trip->status === 'pending')
                    <div class="speed-badge" style="background:#FEF3C7;color:#92400E">⏳ Attente</div>
                @else
                    <div class="speed-badge gps-missing">✅ Terminé</div>
                @endif
                @if($trip->distance_km)
                    <div style="font-size:11px;color:#9CA3AF">{{ number_format($trip->distance_km, 1) }} km</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>

{{-- Panneau détail --}}
@if($selectedTrip)
@php
    $t       = $selectedTrip;
    $drv     = $t->user;
    $dPrf    = $drv?->profile;
    $drvName = trim(($dPrf?->first_name??'').(' '.($dPrf?->last_name??''))) ?: 'Conducteur';
    $palette2 = ['#1A5FB4','#10B981','#F59E0B','#6366F1','#EF4444','#EC4899'];
    $drvBg   = $palette2[abs(crc32($drvName))%count($palette2)];
    $drvInit = strtoupper(substr($dPrf?->first_name??'C',0,1).substr($dPrf?->last_name??'',0,1));
    $hasGps2 = $t->current_latitude && $t->current_longitude;
    $statusLabels = ['active'=>'🚗 En cours','pending'=>'⏳ En attente','completed'=>'✅ Terminé','cancelled'=>'❌ Annulé'];
@endphp
<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    <div class="panel-head">
        <h2>Détail du trajet</h2>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>
    <div class="panel-body">

        {{-- Route hero --}}
        <div style="background:linear-gradient(135deg,#1A5FB4,#0F4A9E);border-radius:12px;padding:16px 18px;color:#fff;margin-bottom:20px">
            <div style="font-size:16px;font-weight:700;margin-bottom:6px">
                {{ $t->departure_city }} <span style="color:#FF7A45">→</span> {{ $t->arrival_city }}
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                <span style="background:rgba(255,255,255,.15);padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600">
                    {{ $statusLabels[$t->status] ?? $t->status }}
                </span>
                @if($t->distance_km)<span style="font-size:12px;opacity:.8">📏 {{ number_format($t->distance_km, 1) }} km</span> @endif
                <span style="font-size:12px;opacity:.8">👥 {{ $t->bookings->count() }} passager(s)</span>
            </div>
        </div>

        {{-- GPS --}}
        <div class="panel-section">
            <div class="panel-section__title">Position GPS</div>
            @if($hasGps2)
            <div class="gps-coords-box">
                <div style="font-size:10px;color:rgba(255,255,255,.5);margin-bottom:6px;text-transform:uppercase">Coordonnées actuelles</div>
                <div class="gps-coords">{{ number_format($t->current_latitude, 6) }}, {{ number_format($t->current_longitude, 6) }}</div>
                @if($t->current_speed !== null)
                <div style="margin-top:6px;font-size:13px;color:#FCD34D;font-weight:700">⚡ {{ number_format($t->current_speed, 1) }} km/h</div>
                @endif
                @if($t->location_updated_at)
                <div style="margin-top:4px;font-size:11px;color:rgba(255,255,255,.45)">Mis à jour {{ $t->location_updated_at->diffForHumans() }}</div>
                @endif
            </div>
            <button class="locate-btn" onclick="window.flyToSelected()">
                🗺️ Localiser sur la carte
            </button>
            @elseif($t->departure_latitude)
            <div class="gps-coords-box">
                <div style="font-size:10px;color:rgba(255,255,255,.5);margin-bottom:6px">Point de départ</div>
                <div class="gps-coords">{{ number_format($t->departure_latitude, 6) }}, {{ number_format($t->departure_longitude, 6) }}</div>
            </div>
            <button class="locate-btn" onclick="window.flyToSelected()">
                📍 Voir le point de départ
            </button>
            @else
            <div class="panel-card" style="text-align:center;color:#9CA3AF;padding:20px">
                <div style="font-size:26px;margin-bottom:6px">📡</div>
                <div style="font-size:13px">Aucune position GPS disponible</div>
            </div>
            @endif
        </div>

        {{-- Points de départ/arrivée --}}
        <div class="panel-section">
            <div class="panel-section__title">Itinéraire</div>
            <div class="panel-card">
                <div style="display:flex;flex-direction:column;gap:10px">
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:28px;height:28px;background:#D1FAE5;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0">🟢</div>
                        <div>
                            <div style="font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.4px">Départ</div>
                            <div style="font-size:13px;font-weight:600;color:#111827">{{ $t->departure_city }}{{ $t->departure_neighborhood ? ', '.$t->departure_neighborhood : '' }}</div>
                            @if($t->departure_time)<div style="font-size:11px;color:#6B7280">{{ $t->departure_time->format('H:i') }}</div>@endif
                        </div>
                    </div>
                    <div style="border-left:2px dashed #E5E7EB;margin-left:14px;height:14px"></div>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:28px;height:28px;background:#FEE2E2;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0">🔴</div>
                        <div>
                            <div style="font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.4px">Arrivée</div>
                            <div style="font-size:13px;font-weight:600;color:#111827">{{ $t->arrival_city }}{{ $t->arrival_neighborhood ? ', '.$t->arrival_neighborhood : '' }}</div>
                            @if($t->estimated_arrival_time)<div style="font-size:11px;color:#6B7280">~{{ $t->estimated_arrival_time->format('H:i') }}</div>@endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Conducteur --}}
        <div class="panel-section">
            <div class="panel-section__title">Conducteur</div>
            <div class="panel-card" style="display:flex;align-items:center;gap:12px">
                <div style="width:42px;height:42px;border-radius:50%;background:{{ $drvBg }};color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0">{{ $drvInit }}</div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:#111827">{{ $drvName }}</div>
                    <div style="font-size:12px;color:#6B7280">{{ $drv?->phone ?? '—' }}</div>
                </div>
            </div>
        </div>

        {{-- Détails --}}
        <div class="panel-section">
            <div class="panel-section__title">Informations</div>
            <div class="panel-card">
                <div class="info-grid">
                    <div class="info-item"><label>Places totales</label><span>{{ $t->total_seats ?? '—' }}</span></div>
                    <div class="info-item"><label>Passagers</label><span>{{ $t->bookings->count() }}</span></div>
                    <div class="info-item"><label>Prix/place</label><span style="color:#1A5FB4">{{ number_format($t->price_per_seat ?? 0) }} F</span></div>
                    <div class="info-item"><label>Distance</label><span>{{ $t->distance_km ? number_format($t->distance_km,1).' km' : '—' }}</span></div>
                </div>
            </div>
        </div>

    </div>
</div>
@endif

@script
<script>
// ─── Constantes ────────────────────────────────────────────────────────────────
const COTONOU    = [6.3656, 2.4183];
const OSRM_BASE  = 'https://router.project-osrm.org/route/v1/driving';

// ─── État ──────────────────────────────────────────────────────────────────────
let leafletMap    = null;
let markers       = {};   // uuid → L.marker véhicule
let depMarkers    = {};   // uuid → L.marker départ
let arrMarkers    = {};   // uuid → L.marker arrivée
let routeLines    = {};   // uuid → L.polyline itinéraire OSRM planifié
let gpsLines      = {};   // uuid → { polyline, coords[] } chemin GPS réel
let focusedUuid   = null;
let focusedPath   = null; // L.polyline surbrillance au focus
let routeCache    = {};   // uuid → [[lat,lng], ...] itinéraire OSRM mis en cache
let fetchQueue    = new Set(); // uuids en cours de fetch pour éviter les doublons

// ─── Init carte ────────────────────────────────────────────────────────────────
function initMap() {
    if (leafletMap) return;
    leafletMap = L.map('minizon-map', { zoomControl: true }).setView(COTONOU, 8);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(leafletMap);
}

// ─── OSRM : récupérer l'itinéraire par les routes réelles ─────────────────────
async function fetchOsrmRoute(depLat, depLng, arrLat, arrLng) {
    // OSRM attend lng,lat (inverse de Leaflet)
    const url = `${OSRM_BASE}/${depLng},${depLat};${arrLng},${arrLat}?overview=full&geometries=geojson`;
    try {
        const res  = await fetch(url, { signal: AbortSignal.timeout(8000) });
        const data = await res.json();
        if (data.code === 'Ok' && data.routes?.[0]) {
            // Retourner en [lat,lng] pour Leaflet
            return data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
        }
    } catch (_) {}
    return null;
}

// ─── Afficher/mettre à jour l'itinéraire OSRM d'un trajet ─────────────────────
async function showPlannedRoute(pos) {
    const { uuid, departure_lat: dLat, departure_lng: dLng,
            arrival_lat: aLat,   arrival_lng: aLng, status } = pos;

    if (!dLat || !dLng || !aLat || !aLng) return;
    if (fetchQueue.has(uuid)) return; // déjà en cours

    // Couleur et style selon le statut
    const styles = {
        pending:   { color: '#F59E0B', weight: 4, opacity: 0.75, dashArray: '10 6' },
        active:    { color: '#9CA3AF', weight: 3, opacity: 0.50, dashArray: '6 5'  },
        completed: { color: '#D1D5DB', weight: 2, opacity: 0.40, dashArray: '4 4'  },
    };
    const style = styles[status] ?? styles.active;

    // Utiliser le cache si disponible
    let coords = routeCache[uuid];

    if (!coords) {
        fetchQueue.add(uuid);
        coords = await fetchOsrmRoute(dLat, dLng, aLat, aLng);
        fetchQueue.delete(uuid);
        if (!coords) return;
        routeCache[uuid] = coords;
    }

    // Supprimer l'ancienne polyline si le statut a changé
    if (routeLines[uuid]) {
        leafletMap.removeLayer(routeLines[uuid]);
    }

    // Dessiner sous les marqueurs (pane par défaut overlayPane)
    routeLines[uuid] = L.polyline(coords, style).addTo(leafletMap);
    routeLines[uuid].bringToBack();
}

// ─── Icônes par statut ─────────────────────────────────────────────────────────
function vehicleIcon(status, hasIncident, hasGps) {
    let color, emoji;
    if (hasIncident)                      { color = '#EF4444'; emoji = '⚠️'; }
    else if (!hasGps && status==='active'){ color = '#6B7280'; emoji = '📡'; }
    else if (status === 'active')         { color = '#1A5FB4'; emoji = '🚗'; }
    else if (status === 'pending')        { color = '#F59E0B'; emoji = '⏳'; }
    else if (status === 'completed')      { color = '#6B7280'; emoji = '✅'; }
    else                                  { color = '#6B7280'; emoji = '🚗'; }

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="36" height="46" viewBox="0 0 36 46">
        <ellipse cx="18" cy="43" rx="8" ry="3.5" fill="rgba(0,0,0,.18)"/>
        <path d="M18 1C10.8 1 5 6.8 5 14c0 10.5 13 31 13 31S31 24.5 31 14C31 6.8 25.2 1 18 1z"
              fill="${color}" stroke="#fff" stroke-width="2"/>
        <circle cx="18" cy="14" r="7" fill="rgba(255,255,255,.92)"/>
        <text x="18" y="18" text-anchor="middle" font-size="9" font-family="system-ui">${emoji}</text>
    </svg>`;
    return L.divIcon({ html: svg, className: '', iconSize: [36, 46], iconAnchor: [18, 46], popupAnchor: [0, -48] });
}

function pinIcon(color, emoji) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 26 26">
        <circle cx="13" cy="13" r="11" fill="${color}" stroke="#fff" stroke-width="2"/>
        <text x="13" y="17" text-anchor="middle" font-size="11" font-family="system-ui">${emoji}</text>
    </svg>`;
    return L.divIcon({ html: svg, className: '', iconSize: [26, 26], iconAnchor: [13, 13] });
}

// ─── Popup véhicule ────────────────────────────────────────────────────────────
function buildPopup(pos) {
    const label = { active:'🚗 En cours', pending:'⏳ En attente', completed:'✅ Terminé' }[pos.status] ?? pos.status;
    const speed = pos.speed ? `<div style="font-size:12px;color:#1A5FB4;margin-top:3px">⚡ ${Math.round(pos.speed)} km/h</div>` : '';
    const inc   = pos.has_incident ? `<div style="font-size:12px;color:#DC2626;margin-top:3px">⚠️ Incident signalé</div>` : '';
    const dep   = pos.departure_time ? `<div style="font-size:11px;color:#6B7280;margin-top:2px">Départ : ${pos.departure_time}</div>` : '';
    return `<div style="min-width:200px">
        <div style="font-weight:700;font-size:13px;color:#111827;margin-bottom:3px">${pos.from} → ${pos.to}</div>
        <span style="font-size:11px;background:#F3F4F6;padding:2px 8px;border-radius:10px;color:#374151;font-weight:600">${label}</span>
        <div style="font-size:12px;color:#6B7280;margin-top:5px">${pos.driver_name}${pos.driver_phone ? ' · '+pos.driver_phone : ''}</div>
        ${dep}${speed}${inc}
        <button onclick="window.openPanel(${pos.id})"
            style="margin-top:8px;padding:5px 12px;background:#1A5FB4;color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;width:100%">
            Voir les détails
        </button>
    </div>`;
}

// ─── Mise à jour des marqueurs + routes ────────────────────────────────────────
function updateMarkers(positions) {
    const seen = new Set();

    positions.forEach(pos => {
        if (!pos.lat || !pos.lng) return;
        seen.add(pos.uuid);

        const latlng = [pos.lat, pos.lng];
        const icon   = vehicleIcon(pos.status, pos.has_incident, pos.has_gps);
        const popup  = buildPopup(pos);

        // Marqueur véhicule
        if (markers[pos.uuid]) {
            markers[pos.uuid].setLatLng(latlng).setIcon(icon);
            markers[pos.uuid].getPopup()?.setContent(popup);
        } else {
            markers[pos.uuid] = L.marker(latlng, { icon })
                .addTo(leafletMap)
                .bindPopup(popup, { maxWidth: 240 });
        }

        // Itinéraire OSRM planifié (fetchs asynchrones, mis en cache)
        if (!routeCache[pos.uuid]) {
            showPlannedRoute(pos); // async, non bloquant
        } else if (!routeLines[pos.uuid]) {
            showPlannedRoute(pos); // recréer la polyline si absente
        }

        // Chemin GPS réel (uniquement trajets actifs avec signal)
        if (pos.status === 'active' && pos.has_gps && pos.uuid !== focusedUuid) {
            if (!gpsLines[pos.uuid]) {
                gpsLines[pos.uuid] = {
                    polyline: L.polyline([], { color: '#1A5FB4', weight: 4, opacity: 0.85 }).addTo(leafletMap),
                    coords:   [],
                };
            }
            const c = gpsLines[pos.uuid].coords;
            const l = c[c.length - 1];
            if (!l || l[0] !== pos.lat || l[1] !== pos.lng) {
                c.push(latlng);
                gpsLines[pos.uuid].polyline.setLatLngs(c);
            }
        }

        // Pins départ / arrivée (créés une seule fois)
        if (pos.departure_lat && pos.departure_lng && !depMarkers[pos.uuid]) {
            depMarkers[pos.uuid] = L.marker([pos.departure_lat, pos.departure_lng], { icon: pinIcon('#10B981', '🟢') })
                .addTo(leafletMap).bindPopup(`<b>Départ</b><br>${pos.from}`);
        }
        if (pos.arrival_lat && pos.arrival_lng && !arrMarkers[pos.uuid]) {
            arrMarkers[pos.uuid] = L.marker([pos.arrival_lat, pos.arrival_lng], { icon: pinIcon('#EF4444', '🔴') })
                .addTo(leafletMap).bindPopup(`<b>Arrivée</b><br>${pos.to}`);
        }
    });

    // Supprimer les trajets disparus
    [...Object.keys(markers)].forEach(uuid => {
        if (seen.has(uuid)) return;
        leafletMap.removeLayer(markers[uuid]);     delete markers[uuid];
        if (routeLines[uuid])  { leafletMap.removeLayer(routeLines[uuid]);          delete routeLines[uuid]; }
        if (gpsLines[uuid])    { leafletMap.removeLayer(gpsLines[uuid].polyline);   delete gpsLines[uuid]; }
        if (depMarkers[uuid])  { leafletMap.removeLayer(depMarkers[uuid]);          delete depMarkers[uuid]; }
        if (arrMarkers[uuid])  { leafletMap.removeLayer(arrMarkers[uuid]);          delete arrMarkers[uuid]; }
        delete routeCache[uuid];
    });

    const el = document.getElementById('map-counter');
    if (el) el.textContent = `${seen.size} trajet(s) affiché(s)`;
}

// ─── Focus sur un trajet ───────────────────────────────────────────────────────
async function focusTrip(tripData) {
    focusedUuid = tripData.uuid;
    if (focusedPath) { leafletMap.removeLayer(focusedPath); focusedPath = null; }

    // 1. Priorité : chemin GPS réel (trajet démarré)
    if (tripData.path && tripData.path.length > 1) {
        const coords = tripData.path.map(p => [p.lat, p.lng]);
        focusedPath  = L.polyline(coords, { color: '#1A5FB4', weight: 5, opacity: 0.95 }).addTo(leafletMap);
        const b = focusedPath.getBounds();
        if (b.isValid()) leafletMap.fitBounds(b, { padding: [60, 60] });

    // 2. Itinéraire OSRM (trajet en attente ou actif sans historique)
    } else {
        let coords = routeCache[tripData.uuid];
        if (!coords && tripData.departure_lat && tripData.arrival_lat) {
            coords = await fetchOsrmRoute(
                tripData.departure_lat, tripData.departure_lng,
                tripData.arrival_lat,   tripData.arrival_lng
            );
            if (coords) routeCache[tripData.uuid] = coords;
        }
        if (coords && coords.length > 1) {
            focusedPath = L.polyline(coords, { color: '#1A5FB4', weight: 5, opacity: 0.95 }).addTo(leafletMap);
            const b = focusedPath.getBounds();
            if (b.isValid()) leafletMap.fitBounds(b, { padding: [60, 60] });
        } else if (tripData.lat && tripData.lng) {
            leafletMap.flyTo([tripData.lat, tripData.lng], 14, { duration: 1 });
        } else if (tripData.departure_lat && tripData.departure_lng) {
            leafletMap.flyTo([tripData.departure_lat, tripData.departure_lng], 13, { duration: 1 });
        }
    }

    setTimeout(() => markers[tripData.uuid]?.openPopup(), 1200);
}

// ─── Globaux ───────────────────────────────────────────────────────────────────
window.openPanel = (id) => $wire.view(id);

window.flyToSelected = () => {
    if (!focusedUuid) return;
    const m = markers[focusedUuid];
    if (m) { leafletMap.flyTo(m.getLatLng(), 15, { duration: 1 }); setTimeout(() => m.openPopup(), 1100); }
    document.querySelector('.panel-overlay')?.click();
};

// ─── Événements Livewire ───────────────────────────────────────────────────────
$wire.on('map-positions-updated', ({ positions }) => updateMarkers(positions));
$wire.on('trip-focused',  (tripData) => focusTrip(tripData));
$wire.on('trip-closed',   () => {
    focusedUuid = null;
    if (focusedPath) { leafletMap.removeLayer(focusedPath); focusedPath = null; }
});

// ─── Bootstrap ────────────────────────────────────────────────────────────────
initMap();

const initialPositions = @json($positions);
if (initialPositions.length) {
    updateMarkers(initialPositions);
    const withCoords = initialPositions.filter(p => p.lat && p.lng);
    if (withCoords.length === 1) {
        leafletMap.setView([withCoords[0].lat, withCoords[0].lng], 13);
    } else if (withCoords.length > 1) {
        const b = L.latLngBounds(withCoords.map(p => [p.lat, p.lng]));
        if (b.isValid()) leafletMap.fitBounds(b, { padding: [50, 50] });
    }
}
</script>
@endscript

</div>
