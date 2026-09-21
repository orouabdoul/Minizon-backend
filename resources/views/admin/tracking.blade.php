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
.live-badge{display:flex;align-items:center;gap:6px;background:#D1FAE5;border:1.5px solid #6EE7B7;border-radius:20px;padding:6px 14px;font-size:12px;font-weight:700;color:#065F46}
.live-dot{width:8px;height:8px;border-radius:50%;background:#10B981;animation:pulse 1.5s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;gap:12px}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-value{font-size:22px;font-weight:700;color:#111827}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px}

.filter-bar{background:#fff;border-radius:12px;padding:12px 18px;display:flex;gap:10px;align-items:center;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:18px}
.filter-select{padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;background:#fff;cursor:pointer}
.filter-select:focus{border-color:#1A5FB4}

/* Map container */
.map-container{background:#e9ecef;border-radius:16px;height:480px;margin-bottom:20px;position:relative;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.12)}
#minizon-map{height:100%;width:100%}
.map-legend{position:absolute;bottom:12px;left:12px;z-index:1000;background:rgba(255,255,255,.95);border-radius:10px;padding:10px 14px;box-shadow:0 2px 8px rgba(0,0,0,.15);display:flex;gap:14px;flex-wrap:wrap}
.map-legend-item{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:600;color:#374151}
.legend-dot{width:10px;height:10px;border-radius:50%}
.map-stats-overlay{position:absolute;top:12px;right:12px;z-index:1000;background:rgba(255,255,255,.95);border-radius:10px;padding:10px 14px;box-shadow:0 2px 8px rgba(0,0,0,.15);font-size:12px;color:#374151;font-weight:600}

/* Trip list */
.trip-grid{display:flex;flex-direction:column;gap:8px}
.trip-item{background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;gap:14px;cursor:pointer;border:2px solid transparent;transition:all .15s}
.trip-item:hover{border-color:#BFDBFE;box-shadow:0 3px 10px rgba(0,0,0,.08)}
.trip-item.selected{border-color:#1A5FB4}
.trip-item.no-gps{opacity:.8;border-style:dashed}
.trip-item.has-incident{border-color:#FEE2E2;background:#FFFAFA}

.driver-av{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0}
.trip-content{flex:1;min-width:0}
.trip-route{font-size:13px;font-weight:700;color:#111827}
.trip-driver{font-size:12px;color:#6B7280;margin-top:2px}
.trip-gps{font-size:11px;color:#9CA3AF;margin-top:3px;display:flex;gap:8px;flex-wrap:wrap}
.trip-right{display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0}
.speed-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;display:flex;align-items:center;gap:4px}
.gps-ok{background:#D1FAE5;color:#065F46}
.gps-missing{background:#F3F4F6;color:#9CA3AF}
.incident-badge{background:#FEE2E2;color:#DC2626;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700}

/* Panel */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:480px;max-width:95vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
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
.gps-coords-box{background:linear-gradient(135deg,#0F172A,#1E293B);border-radius:12px;padding:16px;text-align:center;margin-bottom:12px}
.gps-coords{font-family:monospace;font-size:14px;color:#6EE7B7;font-weight:700}
.gps-link{display:inline-block;margin-top:8px;padding:6px 14px;background:rgba(99,102,241,.2);color:#A5B4FC;border-radius:8px;font-size:12px;text-decoration:none}
.gps-link:hover{background:rgba(99,102,241,.35)}
.locate-btn{display:inline-flex;align-items:center;gap:6px;margin-top:10px;padding:8px 16px;background:#1A5FB4;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:background .15s}
.locate-btn:hover{background:#0F4A9E}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}

.empty-state{background:#fff;border-radius:12px;padding:60px;text-align:center;color:#9CA3AF;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.empty-state .icon{font-size:40px;margin-bottom:12px}

/* Leaflet popup override */
.leaflet-popup-content{margin:10px 14px;font-family:'Inter',system-ui,sans-serif}
.leaflet-popup-content-wrapper{border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.15)}
</style>

{{-- Polling div (hidden) — triggers refreshPositions every 5s --}}
<div wire:poll.5000ms="refreshPositions" style="display:none" aria-hidden="true"></div>

<div class="track-wrap">

    {{-- Header --}}
    <div class="track-header">
        <div>
            <h1>Suivi Temps Réel</h1>
            <p>Position GPS des trajets actifs sur la plateforme</p>
        </div>
        <div class="live-badge">
            <div class="live-dot"></div>
            EN DIRECT
        </div>
    </div>

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:#D1FAE5">🚗</div>
            <div>
                <div class="stat-value" style="color:#10B981">{{ number_format($stats['active']) }}</div>
                <div class="stat-label">Trajets actifs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#EFF6FF">📍</div>
            <div>
                <div class="stat-value">{{ number_format($stats['with_gps']) }}</div>
                <div class="stat-label">Avec GPS</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEF3C7">📡</div>
            <div>
                <div class="stat-value" style="{{ $stats['without_gps'] > 0 ? 'color:#D97706' : '' }}">{{ number_format($stats['without_gps']) }}</div>
                <div class="stat-label">Sans signal GPS</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#EDE9FE">⚡</div>
            <div>
                <div class="stat-value">{{ $stats['avg_speed'] > 0 ? number_format($stats['avg_speed'], 0).' km/h' : '—' }}</div>
                <div class="stat-label">Vitesse moyenne</div>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    @if($cities->isNotEmpty())
    <div class="filter-bar">
        <select class="filter-select" wire:model.live="cityFilter">
            <option value="">Toutes les villes</option>
            @foreach($cities as $city)
            <option value="{{ $city }}">{{ $city }}</option>
            @endforeach
        </select>
    </div>
    @endif

    {{-- CARTE LEAFLET — wire:ignore protège le DOM Leaflet des re-renders Livewire --}}
    <div wire:ignore class="map-container">
        <div id="minizon-map"></div>
        <div class="map-legend">
            <div class="map-legend-item"><div class="legend-dot" style="background:#1A5FB4"></div>En cours</div>
            <div class="map-legend-item"><div class="legend-dot" style="background:#F59E0B"></div>En attente</div>
            <div class="map-legend-item"><div class="legend-dot" style="background:#EF4444"></div>Incident</div>
            <div class="map-legend-item"><div class="legend-dot" style="background:#10B981"></div>Départ</div>
            <div class="map-legend-item"><div class="legend-dot" style="background:#EF4444;width:8px;height:8px;border-radius:2px"></div>Arrivée</div>
        </div>
        <div class="map-stats-overlay" id="map-stats-overlay">
            {{ $stats['with_gps'] }} véhicule(s) en direct
        </div>
    </div>

    {{-- Trip list --}}
    @if($trips->isEmpty())
    <div class="empty-state">
        <div class="icon">🚗</div>
        <p>Aucun trajet actif en ce moment</p>
    </div>
    @else
    <div class="trip-grid">
        @foreach($trips as $trip)
        @php
            $driver   = $trip->user;
            $dProfile = $driver?->profile;
            $dName    = trim(($dProfile?->first_name??'').(' '.($dProfile?->last_name??''))) ?: 'Conducteur';
            $dInit    = strtoupper(substr($dProfile?->first_name??'C',0,1).substr($dProfile?->last_name??'',0,1));
            $colors   = ['#1A5FB4','#10B981','#F59E0B','#6366F1','#EF4444','#EC4899'];
            $dBg      = $colors[abs(crc32($dName))%count($colors)];
            $hasGps   = $trip->current_latitude && $trip->current_longitude;
            $hasIncident = $trip->activeIncident !== null;
        @endphp
        <div class="trip-item {{ $selectedId === $trip->id ? 'selected' : '' }} {{ !$hasGps ? 'no-gps' : '' }} {{ $hasIncident ? 'has-incident' : '' }}"
             wire:click="view({{ $trip->id }})">
            <div class="driver-av" style="background:{{ $dBg }}">{{ $dInit }}</div>
            <div class="trip-content">
                <div class="trip-route">
                    {{ $trip->departure_city }} <span style="color:#FF7A45">→</span> {{ $trip->arrival_city }}
                    @if($hasIncident) <span style="color:#DC2626;font-size:11px">⚠️ Incident</span> @endif
                </div>
                <div class="trip-driver">{{ $dName }} · {{ $driver?->phone ?? '—' }}</div>
                <div class="trip-gps">
                    @if($hasGps)
                        <span>📍 {{ number_format($trip->current_latitude, 4) }}, {{ number_format($trip->current_longitude, 4) }}</span>
                    @else
                        <span>📡 Pas de signal GPS</span>
                    @endif
                    @if($trip->location_updated_at)
                        <span>🕐 {{ $trip->location_updated_at->diffForHumans() }}</span>
                    @endif
                    <span>👥 {{ $trip->bookings->count() }} passager(s)</span>
                </div>
            </div>
            <div class="trip-right">
                @if($hasIncident)
                <div class="incident-badge">⚠️ Incident</div>
                @elseif($hasGps && $trip->current_speed !== null)
                <div class="speed-badge gps-ok">⚡ {{ number_format($trip->current_speed, 0) }} km/h</div>
                @elseif($hasGps)
                <div class="speed-badge gps-ok">📍 GPS OK</div>
                @else
                <div class="speed-badge gps-missing">📡 Hors ligne</div>
                @endif
                @if($trip->started_at)
                <div style="font-size:11px;color:#9CA3AF">Démarré {{ $trip->started_at->diffForHumans() }}</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>

{{-- Trip detail panel --}}
@if($selectedTrip)
@php
    $t      = $selectedTrip;
    $drv    = $t->user;
    $dPrf   = $drv?->profile;
    $drvName = trim(($dPrf?->first_name??'').(' '.($dPrf?->last_name??''))) ?: 'Conducteur';
    $colors3 = ['#1A5FB4','#10B981','#F59E0B','#6366F1','#EF4444','#EC4899'];
    $drvBg   = $colors3[abs(crc32($drvName))%count($colors3)];
    $drvInit = strtoupper(substr($dPrf?->first_name??'C',0,1).substr($dPrf?->last_name??'',0,1));
    $hasGps2 = $t->current_latitude && $t->current_longitude;
@endphp
<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    <div class="panel-head">
        <h2>Suivi du trajet</h2>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>
    <div class="panel-body">

        {{-- Route hero --}}
        <div style="background:linear-gradient(135deg,#1A5FB4,#0F4A9E);border-radius:12px;padding:16px 18px;color:#fff;margin-bottom:20px">
            <div style="font-size:16px;font-weight:700;margin-bottom:6px">
                {{ $t->departure_city }} <span style="color:#FF7A45">→</span> {{ $t->arrival_city }}
            </div>
            <div style="display:flex;gap:14px;flex-wrap:wrap">
                @if($t->distance_km)<span style="font-size:12px;opacity:.85">📏 {{ number_format($t->distance_km, 1) }} km</span> @endif
                <span style="font-size:12px;opacity:.85">🕐 {{ $t->started_at?->diffForHumans() ?? '—' }}</span>
                <span style="font-size:12px;opacity:.85">👥 {{ $t->bookings->count() }} passager(s)</span>
            </div>
        </div>

        {{-- Localiser sur la carte --}}
        @if($hasGps2)
        <div class="panel-section">
            <div class="panel-section__title">Position GPS</div>
            <div class="gps-coords-box">
                <div style="font-size:11px;color:rgba(255,255,255,.6);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">Coordonnées actuelles</div>
                <div class="gps-coords">
                    {{ number_format($t->current_latitude, 6) }}, {{ number_format($t->current_longitude, 6) }}
                </div>
                @if($t->current_speed !== null)
                <div style="margin-top:8px;font-size:13px;color:#FCD34D;font-weight:700">⚡ {{ number_format($t->current_speed, 1) }} km/h</div>
                @endif
                @if($t->location_updated_at)
                <div style="margin-top:6px;font-size:11px;color:rgba(255,255,255,.5)">
                    Mis à jour {{ $t->location_updated_at->diffForHumans() }}
                </div>
                @endif
            </div>
            <button class="locate-btn" onclick="window.flyToSelected()">
                🗺️ Localiser sur la carte principale
            </button>
        </div>
        @else
        <div class="panel-section">
            <div class="panel-section__title">Position GPS</div>
            <div class="panel-card" style="text-align:center;color:#9CA3AF;padding:24px">
                <div style="font-size:28px;margin-bottom:8px">📡</div>
                <div style="font-size:13px">Signal GPS non disponible</div>
            </div>
        </div>
        @endif

        {{-- Conducteur --}}
        <div class="panel-section">
            <div class="panel-section__title">Conducteur</div>
            <div class="panel-card" style="display:flex;align-items:center;gap:12px">
                <div style="width:44px;height:44px;border-radius:50%;background:{{ $drvBg }};color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;flex-shrink:0">{{ $drvInit }}</div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:#111827">{{ $drvName }}</div>
                    <div style="font-size:12px;color:#6B7280">{{ $drv?->phone ?? '—' }}</div>
                </div>
            </div>
        </div>

        {{-- Détails --}}
        <div class="panel-section">
            <div class="panel-section__title">Détails du trajet</div>
            <div class="panel-card">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Places totales</label>
                        <span>{{ $t->total_seats }}</span>
                    </div>
                    <div class="info-item">
                        <label>Passagers à bord</label>
                        <span>{{ $t->bookings->count() }}</span>
                    </div>
                    <div class="info-item">
                        <label>Prix/place</label>
                        <span style="color:#1A5FB4">{{ number_format($t->price_per_seat) }} F</span>
                    </div>
                    <div class="info-item">
                        <label>Départ prévu</label>
                        <span>{{ $t->departure_time?->format('H:i') ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endif

@script
<script>
// ─── Constantes ────────────────────────────────────────────────────────────────
const COTONOU = [6.3656, 2.4183];
const BENIN   = [9.3077, 2.3158];

// ─── État global ───────────────────────────────────────────────────────────────
let leafletMap   = null;
let markers      = {};   // uuid → L.marker
let pathLines    = {};   // uuid → { polyline, coords[] }
let depMarkers   = {};   // uuid → L.marker départ
let arrMarkers   = {};   // uuid → L.marker arrivée
let focusedUuid  = null;
let focusedPath  = null; // polyline tracé détaillé du trip focalisé

// ─── Init carte ────────────────────────────────────────────────────────────────
function initMap() {
    if (leafletMap) return;

    leafletMap = L.map('minizon-map', { zoomControl: true }).setView(COTONOU, 8);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(leafletMap);
}

// ─── Fabrique d'icônes ─────────────────────────────────────────────────────────
function vehicleIcon(status, hasIncident) {
    const c = hasIncident ? '#EF4444' : (status === 'pending' ? '#F59E0B' : '#1A5FB4');
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="34" height="44" viewBox="0 0 34 44">
        <ellipse cx="17" cy="41" rx="7" ry="3" fill="rgba(0,0,0,.18)"/>
        <path d="M17 1C10.1 1 4.5 6.6 4.5 13.5c0 9.9 12.5 29.5 12.5 29.5S29.5 23.4 29.5 13.5C29.5 6.6 23.9 1 17 1z" fill="${c}" stroke="#fff" stroke-width="1.5"/>
        <circle cx="17" cy="13.5" r="6.5" fill="#fff"/>
        <text x="17" y="17.5" text-anchor="middle" font-size="9" font-family="system-ui">🚗</text>
    </svg>`;
    return L.divIcon({ html: svg, className: '', iconSize: [34, 44], iconAnchor: [17, 44], popupAnchor: [0, -46] });
}

function pinIcon(color, emoji) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 26 26">
        <circle cx="13" cy="13" r="11" fill="${color}" stroke="#fff" stroke-width="2"/>
        <text x="13" y="17" text-anchor="middle" font-size="11" font-family="system-ui">${emoji}</text>
    </svg>`;
    return L.divIcon({ html: svg, className: '', iconSize: [26, 26], iconAnchor: [13, 13] });
}

// ─── Mise à jour des marqueurs ─────────────────────────────────────────────────
function updateMarkers(positions) {
    const seen = new Set();

    positions.forEach(pos => {
        if (!pos.lat || !pos.lng) return;
        seen.add(pos.uuid);

        const latlng = [pos.lat, pos.lng];
        const icon   = vehicleIcon(pos.status, pos.has_incident);
        const popup  = buildPopup(pos);

        if (markers[pos.uuid]) {
            markers[pos.uuid].setLatLng(latlng).setIcon(icon);
            if (markers[pos.uuid].getPopup()) markers[pos.uuid].getPopup().setContent(popup);
        } else {
            markers[pos.uuid] = L.marker(latlng, { icon })
                .addTo(leafletMap)
                .bindPopup(popup, { maxWidth: 240 });
        }

        // Tracé progressif (seulement si pas de tracé détaillé affiché)
        if (pos.uuid !== focusedUuid) {
            if (!pathLines[pos.uuid]) {
                pathLines[pos.uuid] = {
                    polyline: L.polyline([], { color: '#1A5FB4', weight: 3, opacity: 0.55, dashArray: '4 4' }).addTo(leafletMap),
                    coords: [],
                };
            }
            const coords = pathLines[pos.uuid].coords;
            const last   = coords[coords.length - 1];
            if (!last || last[0] !== pos.lat || last[1] !== pos.lng) {
                coords.push(latlng);
                pathLines[pos.uuid].polyline.setLatLngs(coords);
            }
        }

        // Marqueurs départ/arrivée
        if (pos.departure_lat && pos.departure_lng && !depMarkers[pos.uuid]) {
            depMarkers[pos.uuid] = L.marker([pos.departure_lat, pos.departure_lng], { icon: pinIcon('#10B981', '🟢') })
                .addTo(leafletMap)
                .bindPopup(`<b>Départ</b><br>${pos.from}`);
        }
        if (pos.arrival_lat && pos.arrival_lng && !arrMarkers[pos.uuid]) {
            arrMarkers[pos.uuid] = L.marker([pos.arrival_lat, pos.arrival_lng], { icon: pinIcon('#EF4444', '🔴') })
                .addTo(leafletMap)
                .bindPopup(`<b>Arrivée</b><br>${pos.to}`);
        }
    });

    // Supprimer les marqueurs des trajets terminés/disparus
    Object.keys(markers).forEach(uuid => {
        if (!seen.has(uuid)) {
            leafletMap.removeLayer(markers[uuid]); delete markers[uuid];
            if (pathLines[uuid]) { leafletMap.removeLayer(pathLines[uuid].polyline); delete pathLines[uuid]; }
            if (depMarkers[uuid]) { leafletMap.removeLayer(depMarkers[uuid]); delete depMarkers[uuid]; }
            if (arrMarkers[uuid]) { leafletMap.removeLayer(arrMarkers[uuid]); delete arrMarkers[uuid]; }
        }
    });

    // Mise à jour compteur overlay
    const el = document.getElementById('map-stats-overlay');
    if (el) el.textContent = `${seen.size} véhicule(s) en direct`;
}

// ─── Popup véhicule ────────────────────────────────────────────────────────────
function buildPopup(pos) {
    const speed    = pos.speed ? `<div style="font-size:12px;color:#1A5FB4;margin-top:3px">⚡ ${Math.round(pos.speed)} km/h</div>` : '';
    const incident = pos.has_incident ? `<div style="font-size:12px;color:#DC2626;margin-top:3px">⚠️ Incident signalé</div>` : '';
    const flagged  = pos.is_flagged   ? `<div style="font-size:12px;color:#D97706;margin-top:3px">🚩 Trajet signalé</div>` : '';
    return `<div style="min-width:200px">
        <div style="font-weight:700;font-size:13px;color:#111827">${pos.from} → ${pos.to}</div>
        <div style="font-size:12px;color:#6B7280;margin-top:2px">${pos.driver_name}${pos.driver_phone ? ' · ' + pos.driver_phone : ''}</div>
        ${speed}${incident}${flagged}
        <button onclick="window.openPanel(${pos.id})"
            style="margin-top:8px;padding:5px 12px;background:#1A5FB4;color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;width:100%">
            Voir les détails
        </button>
    </div>`;
}

// ─── Trajet focalisé (depuis le panneau) ───────────────────────────────────────
function focusTrip(tripData) {
    focusedUuid = tripData.uuid;

    // Effacer ancien tracé détaillé
    if (focusedPath) { leafletMap.removeLayer(focusedPath); focusedPath = null; }

    // Dessiner le tracé historique complet
    if (tripData.path && tripData.path.length > 1) {
        const coords = tripData.path.map(p => [p.lat, p.lng]);
        focusedPath = L.polyline(coords, { color: '#1A5FB4', weight: 4, opacity: 0.85 }).addTo(leafletMap);
        leafletMap.fitBounds(focusedPath.getBounds(), { padding: [60, 60] });
    } else if (tripData.lat && tripData.lng) {
        leafletMap.flyTo([tripData.lat, tripData.lng], 14, { duration: 1 });
    }
}

// ─── Exposé globalement pour les boutons dans les popups/panel ─────────────────
window.openPanel      = (id) => $wire.view(id);
window.flyToSelected  = () => {
    if (focusedUuid && markers[focusedUuid]) {
        leafletMap.flyTo(markers[focusedUuid].getLatLng(), 15, { duration: 1 });
        setTimeout(() => { markers[focusedUuid]?.openPopup(); }, 1100);
        // Fermer le panneau pour voir la carte
        document.querySelector('.panel-overlay')?.click();
    }
};

// ─── Événements Livewire ───────────────────────────────────────────────────────
$wire.on('map-positions-updated', ({ positions }) => {
    updateMarkers(positions);
});

$wire.on('trip-focused', (tripData) => {
    focusTrip(tripData);
});

$wire.on('trip-closed', () => {
    focusedUuid = null;
    if (focusedPath) { leafletMap.removeLayer(focusedPath); focusedPath = null; }
});

// ─── Bootstrap ────────────────────────────────────────────────────────────────
initMap();

// Charger les positions initiales depuis PHP
const initialPositions = @json($positions);
if (initialPositions.length) {
    updateMarkers(initialPositions);
    // Zoomer sur les véhicules GPS si présents
    const withGps = initialPositions.filter(p => p.lat && p.lng);
    if (withGps.length === 1) {
        leafletMap.setView([withGps[0].lat, withGps[0].lng], 13);
    } else if (withGps.length > 1) {
        const bounds = L.latLngBounds(withGps.map(p => [p.lat, p.lng]));
        leafletMap.fitBounds(bounds, { padding: [50, 50] });
    }
}
</script>
@endscript

</div>
