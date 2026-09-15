@php
$kpiIcons = [
    'users'      => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'drivers'    => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
    'trips'      => '<circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>',
    'revenue'    => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
    'passengers' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
    'bookings'   => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
    'disputes'   => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    'completed'  => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
];
$statusLabels = [
    'pending'     => ['label' => 'En attente',  'class' => 'badge-warning'],
    'confirmed'   => ['label' => 'Confirmé',    'class' => 'badge-blue'],
    'in_progress' => ['label' => 'En cours',    'class' => 'badge-blue'],
    'completed'   => ['label' => 'Terminé',     'class' => 'badge-success'],
    'cancelled'   => ['label' => 'Annulé',      'class' => 'badge-error'],
];
@endphp

{{-- KPI Cards --}}
<div class="dash-kpi-grid">
    @foreach($kpis as $kpi)
        <div class="kpi-card">
            <div class="kpi-card__header">
                <div class="kpi-card__icon" style="background:{{ $kpi['iconBg'] }}">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="{{ $kpi['iconColor'] }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        {!! $kpiIcons[$kpi['icon']] !!}
                    </svg>
                </div>
                <span class="kpi-card__badge kpi-card__badge--{{ $kpi['variant'] }}">{{ $kpi['badge'] }}</span>
            </div>
            <div>
                <div class="kpi-card__value">{{ $kpi['value'] }}</div>
                <div class="kpi-card__label">{{ $kpi['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>

{{-- Charts row --}}
<div class="dash-charts-grid">
    <div class="chart-card">
        <div class="chart-card__title">Répartition Utilisateurs</div>
        <div class="chart-card__sub">Conducteurs vs Passagers</div>
        <div class="chart-placeholder">
            @php
                $drivers    = collect($kpis)->firstWhere('icon','drivers')['value'] ?? '0';
                $passengers = collect($kpis)->firstWhere('icon','passengers')['value'] ?? '0';
            @endphp
            <div style="text-align:center">
                <div style="display:flex;gap:24px;justify-content:center;margin-bottom:12px;">
                    <div><div style="font-size:22px;font-weight:700;color:#1A5FB4">{{ $drivers }}</div><div style="font-size:12px;color:#6B7684">Conducteurs</div></div>
                    <div style="width:1px;background:#E5E7EB"></div>
                    <div><div style="font-size:22px;font-weight:700;color:#4F46E5">{{ $passengers }}</div><div style="font-size:12px;color:#6B7684">Passagers</div></div>
                </div>
                <div style="font-size:12px;color:#9CA3AF">Graphique disponible prochainement</div>
            </div>
        </div>
    </div>
    <div class="chart-card">
        <div class="chart-card__title">Trajets & Réservations</div>
        <div class="chart-card__sub">Activité de la plateforme</div>
        <div class="chart-placeholder">
            @php
                $active    = collect($kpis)->firstWhere('icon','trips')['value'] ?? '0';
                $completed = collect($kpis)->firstWhere('icon','completed')['value'] ?? '0';
                $bookings  = collect($kpis)->firstWhere('icon','bookings')['value'] ?? '0';
            @endphp
            <div style="text-align:center">
                <div style="display:flex;gap:20px;justify-content:center;margin-bottom:12px;">
                    <div><div style="font-size:20px;font-weight:700;color:#9333EA">{{ $active }}</div><div style="font-size:12px;color:#6B7684">Actifs</div></div>
                    <div style="width:1px;background:#E5E7EB"></div>
                    <div><div style="font-size:20px;font-weight:700;color:#17A398">{{ $completed }}</div><div style="font-size:12px;color:#6B7684">Terminés</div></div>
                    <div style="width:1px;background:#E5E7EB"></div>
                    <div><div style="font-size:20px;font-weight:700;color:#0D9488">{{ $bookings }}</div><div style="font-size:12px;color:#6B7684">Réservations</div></div>
                </div>
                <div style="font-size:12px;color:#9CA3AF">Graphique disponible prochainement</div>
            </div>
        </div>
    </div>
</div>

{{-- Activity table --}}
<div class="activity-card">
    <div class="activity-card__header">
        <div class="activity-card__title">Activité Récente — Trajets</div>
        <a href="/admin/trips" style="font-size:13px;color:#1A5FB4;text-decoration:none;font-weight:500;">Voir tout →</a>
    </div>
    @if(count($activity) > 0)
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th>Conducteur</th>
                        <th>Départ</th>
                        <th>Arrivée</th>
                        <th>Statut</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activity as $row)
                        <tr>
                            <td style="font-weight:500">{{ $row['driver'] }}</td>
                            <td style="color:#6B7684">{{ $row['from'] }}</td>
                            <td style="color:#6B7684">{{ $row['to'] }}</td>
                            <td>
                                @php $s = $statusLabels[$row['status']] ?? ['label' => $row['status'], 'class' => 'badge-blue']; @endphp
                                <span class="badge {{ $s['class'] }}">{{ $s['label'] }}</span>
                            </td>
                            <td style="color:#6B7684;font-size:12px">{{ $row['date'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div style="padding:40px;text-align:center;color:#9CA3AF;font-size:14px">
            Aucune activité récente
        </div>
    @endif
</div>
