<div>
<style>
/* ── Alerts ───────────────────────────────────────────── */
.alert-bar { display:flex; flex-direction:column; gap:8px; margin-bottom:20px; }
.alert-item {
    display:flex; align-items:center; gap:12px;
    padding:12px 16px; border-radius:10px; font-size:13px; font-weight:500;
}
.alert-item--error   { background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; }
.alert-item--warning { background:#FFFBEB; border:1px solid #FDE68A; color:#92400E; }
.alert-item--info    { background:#EFF6FF; border:1px solid #BFDBFE; color:#1E40AF; }
.alert-item__dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.alert-item--error   .alert-item__dot { background:#DC2626; }
.alert-item--warning .alert-item__dot { background:#D97706; }
.alert-item--info    .alert-item__dot { background:#2563EB; }
.alert-item__text { flex:1; }
.alert-item__link {
    font-size:12px; font-weight:600; padding:4px 12px;
    border-radius:6px; text-decoration:none; white-space:nowrap;
}
.alert-item--error   .alert-item__link { background:#DC2626; color:white; }
.alert-item--warning .alert-item__link { background:#D97706; color:white; }
.alert-item--info    .alert-item__link { background:#2563EB; color:white; }

/* ── KPI Cards ────────────────────────────────────────── */
.kpi-grid-new { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
@media(max-width:1100px){ .kpi-grid-new { grid-template-columns:repeat(2,1fr); } }
@media(max-width:600px) { .kpi-grid-new { grid-template-columns:1fr; } }

.kpi-new {
    background:#fff; border-radius:14px; padding:20px;
    border:1px solid #F3F4F6; position:relative; overflow:hidden;
    transition:box-shadow .2s; text-decoration:none; display:block;
}
.kpi-new:hover { box-shadow:0 4px 20px rgba(0,0,0,0.08); }
.kpi-new--urgent { border-color:#FECACA; background:#FFFAFA; }
.kpi-new__top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; }
.kpi-new__icon { width:44px; height:44px; border-radius:11px; display:flex; align-items:center; justify-content:center; }
.kpi-new__trend {
    display:flex; align-items:center; gap:4px;
    font-size:11px; font-weight:600; padding:4px 8px; border-radius:20px;
}
.kpi-new__trend--up      { background:rgba(22,163,74,0.1);  color:#16A34A; }
.kpi-new__trend--down    { background:rgba(220,38,38,0.1);  color:#DC2626; }
.kpi-new__trend--neutral { background:rgba(107,114,128,0.1);color:#6B7280; }
.kpi-new__value { font-size:30px; font-weight:700; color:#1F2933; line-height:1; margin-bottom:4px; }
.kpi-new__label { font-size:13px; font-weight:500; color:#6B7684; }
.kpi-new__sub   { font-size:11px; color:#9CA3AF; margin-top:4px; }
.kpi-new__bar { position:absolute; bottom:0; left:0; right:0; height:3px; background:var(--bar-color,#1A5FB4); opacity:0.3; }

/* ── Main Grid ────────────────────────────────────────── */
.dash-main-grid { display:grid; grid-template-columns:1fr 340px; gap:16px; margin-bottom:24px; }
@media(max-width:1100px){ .dash-main-grid { grid-template-columns:1fr; } }

/* ── Feed ─────────────────────────────────────────────── */
.feed-card { background:#fff; border-radius:14px; border:1px solid #F3F4F6; overflow:hidden; }
.feed-card__header {
    padding:16px 20px; border-bottom:1px solid #F3F4F6;
    display:flex; justify-content:space-between; align-items:center;
}
.feed-card__title { font-size:14px; font-weight:600; color:#1F2933; display:flex; align-items:center; gap:8px; }
.feed-card__live  { width:8px; height:8px; border-radius:50%; background:#17A398; animation:pulse 2s infinite; }
.feed-card__link  { font-size:12px; color:#1A5FB4; text-decoration:none; font-weight:500; }
.feed-item {
    display:flex; align-items:center; gap:12px;
    padding:12px 20px; border-bottom:1px solid #FAFAFA; transition:background .15s;
}
.feed-item:last-child { border-bottom:none; }
.feed-item:hover { background:#FAFBFF; }
.feed-item__icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.feed-item__body { flex:1; min-width:0; }
.feed-item__title { font-size:13px; font-weight:600; color:#1F2933; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.feed-item__sub   { font-size:11px; color:#9CA3AF; margin-top:1px; }
.feed-item__right { display:flex; flex-direction:column; align-items:flex-end; gap:4px; }
.feed-item__time  { font-size:11px; color:#9CA3AF; white-space:nowrap; }

/* ── Panels droite ────────────────────────────────────── */
.panels-col { display:flex; flex-direction:column; gap:12px; }
.mini-panel { background:#fff; border-radius:14px; border:1px solid #F3F4F6; overflow:hidden; }
.mini-panel__header { padding:12px 16px; border-bottom:1px solid #F9FAFB; }
.mini-panel__title  { font-size:13px; font-weight:600; color:#1F2933; }
.mini-panel__body   { padding:4px 0; }
.mini-row {
    display:flex; justify-content:space-between; align-items:center;
    padding:7px 16px; font-size:12px;
}
.mini-row__label { color:#6B7684; }
.mini-row__value { font-weight:700; font-size:13px; }
.mini-row + .mini-row { border-top:1px solid #FAFAFA; }

/* ── Quick Actions ────────────────────────────────────── */
.quick-actions { display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:24px; }
@media(max-width:1100px){ .quick-actions { grid-template-columns:repeat(3,1fr); } }
@media(max-width:600px) { .quick-actions { grid-template-columns:repeat(2,1fr); } }

.qa-btn {
    background:#fff; border:1px solid #F3F4F6; border-radius:12px;
    padding:16px 12px; display:flex; flex-direction:column; align-items:center; gap:8px;
    text-decoration:none; transition:all .2s; cursor:pointer;
}
.qa-btn:hover { border-color:var(--qa-color); background:var(--qa-bg); box-shadow:0 2px 12px rgba(0,0,0,0.06); }
.qa-btn__icon { width:40px; height:40px; border-radius:10px; background:var(--qa-bg2); display:flex; align-items:center; justify-content:center; }
.qa-btn__label { font-size:11px; font-weight:600; color:#374151; text-align:center; line-height:1.3; }

/* ── Financial row ────────────────────────────────────── */
.fin-row { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px; margin-bottom:24px; }
@media(max-width:900px){ .fin-row { grid-template-columns:1fr 1fr; } }

.fin-card { background:#fff; border-radius:14px; border:1px solid #F3F4F6; padding:20px; }
.fin-card__label { font-size:12px; color:#9CA3AF; font-weight:500; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px; }
.fin-card__value { font-size:22px; font-weight:700; color:#1F2933; }
.fin-card__bar   { height:4px; border-radius:2px; margin-top:12px; background:var(--fin-color,#1A5FB4); opacity:0.3; }

/* ── Revenue chart ────────────────────────────────────── */
.rev-chart-card { background:#fff; border-radius:14px; border:1px solid #F3F4F6; padding:20px; margin-bottom:24px; }
.rev-chart-card__header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.rev-chart-card__title  { font-size:14px; font-weight:600; color:#1F2933; }
.rev-chart-card__sub    { font-size:12px; color:#9CA3AF; }
.bar-chart { display:flex; align-items:flex-end; gap:8px; height:100px; }
.bar-chart__col { display:flex; flex-direction:column; align-items:center; gap:4px; flex:1; }
.bar-chart__bar { width:100%; border-radius:4px 4px 0 0; background:#1A5FB4; min-height:4px; transition:opacity .2s; }
.bar-chart__bar:hover { opacity:0.8; }
.bar-chart__label { font-size:10px; color:#9CA3AF; }

@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
</style>

{{-- ① ALERTES URGENTES ──────────────────────────────────── --}}
@if(count($alerts) > 0)
<div class="alert-bar">
    @foreach($alerts as $alert)
    <div class="alert-item alert-item--{{ $alert['type'] }}">
        <span class="alert-item__dot"></span>
        <span class="alert-item__text">{{ $alert['message'] }}</span>
        <a href="{{ $alert['link'] }}" class="alert-item__link">{{ $alert['label'] }}</a>
    </div>
    @endforeach
</div>
@endif

{{-- ② 4 KPI PRIORITAIRES ────────────────────────────────── --}}
@php
$kpiIcons = [
    'trips'    => '<circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>',
    'revenue'  => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
    'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'disputes' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
];
$trendIcons = [
    'up'      => '↑',
    'down'    => '↓',
    'neutral' => '—',
];
@endphp

<div class="kpi-grid-new">
    @foreach($kpis as $kpi)
    <a href="{{ $kpi['link'] }}" class="kpi-new {{ ($kpi['urgent'] ?? false) ? 'kpi-new--urgent' : '' }}"
       style="--bar-color:{{ $kpi['iconColor'] }}">
        <div class="kpi-new__top">
            <div class="kpi-new__icon" style="background:{{ $kpi['iconBg'] }}">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="{{ $kpi['iconColor'] }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    {!! $kpiIcons[$kpi['icon']] !!}
                </svg>
            </div>
            <span class="kpi-new__trend kpi-new__trend--{{ $kpi['trend'] }}">
                {{ $trendIcons[$kpi['trend']] }} {{ $kpi['trend'] === 'up' ? 'Actif' : ($kpi['trend'] === 'down' ? 'Alerte' : 'Stable') }}
            </span>
        </div>
        <div class="kpi-new__value">{{ $kpi['value'] }}</div>
        <div class="kpi-new__label">{{ $kpi['label'] }}</div>
        <div class="kpi-new__sub">{{ $kpi['sub'] }}</div>
        <div class="kpi-new__bar"></div>
    </a>
    @endforeach
</div>

{{-- ③ GRILLE PRINCIPALE : FEED + MINI PANNEAUX ──────────── --}}
@php
$statusMap = [
    'pending'     => ['label'=>'En attente', 'bg'=>'#FEF9C3','color'=>'#92400E'],
    'confirmed'   => ['label'=>'Confirmé',   'bg'=>'#DBEAFE','color'=>'#1E40AF'],
    'in_progress' => ['label'=>'En cours',   'bg'=>'#EDE9FE','color'=>'#6D28D9'],
    'completed'   => ['label'=>'Terminé',    'bg'=>'#DCFCE7','color'=>'#166534'],
    'cancelled'   => ['label'=>'Annulé',     'bg'=>'#FEE2E2','color'=>'#991B1B'],
    'verified'    => ['label'=>'Vérifié',    'bg'=>'#DCFCE7','color'=>'#166534'],
];
@endphp

<div class="dash-main-grid">
    {{-- Feed activité --}}
    <div class="feed-card">
        <div class="feed-card__header">
            <div class="feed-card__title">
                <span class="feed-card__live"></span>
                Activité en temps réel
            </div>
            <a href="/admin/trips" class="feed-card__link">Voir tout →</a>
        </div>

        @if(count($recentFeed) > 0)
            @foreach($recentFeed as $item)
            @php
                $isTripType = $item['type'] === 'trip';
                $st = $statusMap[$item['status']] ?? ['label'=>$item['status'],'bg'=>'#F3F4F6','color'=>'#374151'];
            @endphp
            <div class="feed-item">
                <div class="feed-item__icon" style="background:{{ $isTripType ? '#EDE9FE' : '#DCFCE7' }}">
                    @if($isTripType)
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7C3AED" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>
                    </svg>
                    @else
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                    @endif
                </div>
                <div class="feed-item__body">
                    <div class="feed-item__title">{{ $item['title'] }}</div>
                    <div class="feed-item__sub">{{ $item['sub'] }}</div>
                </div>
                <div class="feed-item__right">
                    <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;background:{{ $st['bg'] }};color:{{ $st['color'] }}">
                        {{ $st['label'] }}
                    </span>
                    <span class="feed-item__time">{{ $item['time'] }}</span>
                </div>
            </div>
            @endforeach
        @else
            <div style="padding:40px;text-align:center;color:#9CA3AF;font-size:13px">Aucune activité récente</div>
        @endif
    </div>

    {{-- Mini panneaux droite --}}
    <div class="panels-col">
        {{-- Utilisateurs --}}
        <div class="mini-panel">
            <div class="mini-panel__header" style="display:flex;justify-content:space-between;align-items:center">
                <div class="mini-panel__title">Utilisateurs</div>
                <a href="/admin/users" style="font-size:11px;color:#1A5FB4;text-decoration:none">Voir →</a>
            </div>
            <div class="mini-panel__body">
                @foreach($miniPanels['users'] as $row)
                <div class="mini-row">
                    <span class="mini-row__label">{{ $row['label'] }}</span>
                    <span class="mini-row__value" style="color:{{ $row['color'] }}">{{ $row['value'] }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Trajets --}}
        <div class="mini-panel">
            <div class="mini-panel__header" style="display:flex;justify-content:space-between;align-items:center">
                <div class="mini-panel__title">Trajets & Réservations</div>
                <a href="/admin/trips" style="font-size:11px;color:#1A5FB4;text-decoration:none">Voir →</a>
            </div>
            <div class="mini-panel__body">
                @foreach($miniPanels['trips'] as $row)
                <div class="mini-row">
                    <span class="mini-row__label">{{ $row['label'] }}</span>
                    <span class="mini-row__value" style="color:{{ $row['color'] }}">{{ $row['value'] }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Top conducteurs --}}
        <div class="mini-panel">
            <div class="mini-panel__header" style="display:flex;justify-content:space-between;align-items:center">
                <div class="mini-panel__title">Top Conducteurs</div>
                <a href="/admin/drivers" style="font-size:11px;color:#1A5FB4;text-decoration:none">Voir →</a>
            </div>
            <div class="mini-panel__body">
                @if(count($topDrivers) > 0)
                    @foreach($topDrivers as $i => $d)
                    <div class="mini-row">
                        <span class="mini-row__label">
                            <span style="color:#9CA3AF;font-size:10px;margin-right:6px;">{{ $i+1 }}.</span>
                            {{ $d['name'] }}
                        </span>
                        <span class="mini-row__value" style="color:#1A5FB4">{{ $d['trips'] }} trajets</span>
                    </div>
                    @endforeach
                @else
                    <div style="padding:12px 16px;font-size:12px;color:#9CA3AF">Aucune donnée</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ④ ACTIONS RAPIDES ────────────────────────────────────── --}}
<div class="quick-actions">
    @php $qas = [
        ['label'=>'Valider KYC',        'link'=>'/admin/drivers',       'color'=>'#1A5FB4','bg'=>'rgba(26,95,180,0.08)','bg2'=>'rgba(26,95,180,0.12)',
         'icon'=>'<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>'],
        ['label'=>'Résoudre litiges',   'link'=>'/admin/disputes',      'color'=>'#DC2626','bg'=>'rgba(220,38,38,0.06)','bg2'=>'rgba(220,38,38,0.10)',
         'icon'=>'<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'],
        ['label'=>'Suivi temps réel',   'link'=>'/admin/tracking',      'color'=>'#7C3AED','bg'=>'rgba(124,58,237,0.06)','bg2'=>'rgba(124,58,237,0.10)',
         'icon'=>'<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>'],
        ['label'=>'Notification',       'link'=>'/admin/notifications', 'color'=>'#D97706','bg'=>'rgba(217,119,6,0.06)','bg2'=>'rgba(217,119,6,0.10)',
         'icon'=>'<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'],
        ['label'=>'Rapports',           'link'=>'/admin/reports',       'color'=>'#0D9488','bg'=>'rgba(13,148,136,0.06)','bg2'=>'rgba(13,148,136,0.10)',
         'icon'=>'<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
        ['label'=>'Paramètres',         'link'=>'/admin/settings',      'color'=>'#6B7684','bg'=>'rgba(107,114,132,0.06)','bg2'=>'rgba(107,114,132,0.10)',
         'icon'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'],
    ]; @endphp

    @foreach($qas as $qa)
    <a href="{{ $qa['link'] }}" class="qa-btn" style="--qa-color:{{ $qa['color'] }};--qa-bg:{{ $qa['bg'] }};--qa-bg2:{{ $qa['bg2'] }}">
        <div class="qa-btn__icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="{{ $qa['color'] }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                {!! $qa['icon'] !!}
            </svg>
        </div>
        <span class="qa-btn__label">{{ $qa['label'] }}</span>
    </a>
    @endforeach
</div>

{{-- ⑤ FINANCIERS + GRAPHIQUE ────────────────────────────── --}}
<div class="rev-chart-card">
    <div class="rev-chart-card__header">
        <div>
            <div class="rev-chart-card__title">Revenus plateforme — 7 derniers jours</div>
            <div class="rev-chart-card__sub">Commissions perçues par jour</div>
        </div>
        <a href="/admin/payments" style="font-size:12px;color:#1A5FB4;text-decoration:none;font-weight:500">Voir paiements →</a>
    </div>
    @php
        $maxRev = max(array_column($revenue7d, 'revenue') ?: [1]);
    @endphp
    <div class="bar-chart">
        @foreach($revenue7d as $day)
        @php $pct = $maxRev > 0 ? max(4, ($day['revenue'] / $maxRev) * 100) : 4; @endphp
        <div class="bar-chart__col">
            <div class="bar-chart__bar" style="height:{{ $pct }}px"
                 title="{{ $day['label'] }} : {{ number_format($day['revenue']) }} FCFA"></div>
            <span class="bar-chart__label">{{ $day['label'] }}</span>
        </div>
        @endforeach
    </div>
</div>

<div class="fin-row">
    <div class="fin-card" style="--fin-color:#1A5FB4">
        <div class="fin-card__label">Volume total</div>
        <div class="fin-card__value">{{ $financials['volume'] }}</div>
        <div class="fin-card__bar"></div>
    </div>
    <div class="fin-card" style="--fin-color:#16A34A">
        <div class="fin-card__label">Revenus plateforme</div>
        <div class="fin-card__value">{{ $financials['revenue'] }}</div>
        <div class="fin-card__bar" style="background:#16A34A"></div>
    </div>
    <div class="fin-card" style="--fin-color:#D97706">
        <div class="fin-card__label">En escrow (bloqué)</div>
        <div class="fin-card__value">{{ $financials['escrow'] }}</div>
        <div class="fin-card__bar" style="background:#D97706"></div>
    </div>
    <div class="fin-card" style="--fin-color:#DC2626">
        <div class="fin-card__label">Remboursés</div>
        <div class="fin-card__value">{{ $financials['refunded'] }}</div>
        <div class="fin-card__bar" style="background:#DC2626"></div>
    </div>
</div>

</div>
