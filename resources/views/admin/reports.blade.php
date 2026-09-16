<div>
<style>
.rpt-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.rpt-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.rpt-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.rpt-header p{font-size:13px;color:#6B7280;margin:0}
.period-tabs{display:flex;gap:6px;background:#fff;border-radius:10px;padding:4px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.period-tab{padding:7px 16px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;border:none;background:transparent;color:#6B7280;transition:all .15s}
.period-tab.active{background:#1A5FB4;color:#fff}
.period-tab:hover:not(.active){background:#F3F4F6}

.ov-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.ov-card{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.ov-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:10px}
.ov-value{font-size:24px;font-weight:700;color:#111827;line-height:1.1}
.ov-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;margin-top:4px}
.ov-delta{font-size:12px;font-weight:600;margin-top:6px}
.ov-delta.up{color:#10B981}
.ov-delta.down{color:#EF4444}

.row-2col{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}
.row-3col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;margin-bottom:18px}
.chart-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.chart-card h3{font-size:14px;font-weight:600;color:#374151;margin:0 0 16px}

.bar-item{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.bar-label{font-size:12px;color:#374151;min-width:120px;flex-shrink:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bar-track{flex:1;height:8px;background:#F3F4F6;border-radius:4px;overflow:hidden}
.bar-fill{height:100%;border-radius:4px;background:linear-gradient(90deg,#1A5FB4,#3B82F6)}
.bar-count{font-size:11px;color:#6B7280;min-width:36px;text-align:right}

.donut-legend{display:flex;flex-direction:column;gap:6px}
.legend-item{display:flex;align-items:center;gap:8px;font-size:12px;color:#374151}
.legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}

.rev-chart{display:flex;flex-direction:column;gap:4px}
.rev-row{display:flex;align-items:center;gap:8px;font-size:11px}
.rev-day{min-width:50px;color:#9CA3AF}
.rev-bar-track{flex:1;height:18px;background:#F3F4F6;border-radius:4px;overflow:hidden;position:relative}
.rev-bar-fill{height:100%;background:linear-gradient(90deg,#1A5FB4,#3B82F6);border-radius:4px}
.rev-val{min-width:80px;text-align:right;color:#374151;font-weight:600}

.kpi-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px}
.kpi-card{background:#fff;border-radius:12px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.06);text-align:center}
.kpi-value{font-size:22px;font-weight:700;color:#111827;margin-bottom:4px}
.kpi-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px}

.top-driver-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #F3F4F6}
.top-driver-item:last-child{border-bottom:none}
.top-rank{width:24px;height:24px;border-radius:50%;background:#EFF6FF;color:#1A5FB4;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.top-rank.gold{background:#FEF3C7;color:#D97706}
.top-rank.silver{background:#F3F4F6;color:#6B7280}
.top-rank.bronze{background:#FEF3C7;color:#92400E}
.top-avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0}
.top-name{font-size:13px;font-weight:600;color:#111827}
.top-sub{font-size:11px;color:#9CA3AF}
.top-count{margin-left:auto;font-size:14px;font-weight:700;color:#1A5FB4}

.alert-kpi{background:#FEE2E2;border:1.5px solid #FECACA;border-radius:10px;padding:12px 16px;margin-bottom:4px;font-size:12px;color:#991B1B;display:flex;align-items:center;gap:8px}
</style>

<div class="rpt-wrap">

    {{-- Header + Period selector --}}
    <div class="rpt-header">
        <div>
            <h1>Rapports & Analytiques</h1>
            <p>Vue d'ensemble de l'activité de la plateforme</p>
        </div>
        <div class="period-tabs">
            @foreach(['7' => '7 jours', '30' => '30 jours', '90' => '90 jours', '365' => '1 an'] as $val => $lbl)
                <button class="period-tab {{ $period == $val ? 'active' : '' }}"
                        wire:click="$set('period','{{ $val }}')">{{ $lbl }}</button>
            @endforeach
        </div>
    </div>

    {{-- KPI Overview --}}
    <div class="ov-grid">
        <div class="ov-card">
            <div class="ov-icon" style="background:#EFF6FF">👤</div>
            <div class="ov-value">{{ number_format($overview['users_total']) }}</div>
            <div class="ov-label">Utilisateurs total</div>
            <div class="ov-delta up">+{{ number_format($overview['users_new']) }} ces {{ $period }}j</div>
        </div>
        <div class="ov-card">
            <div class="ov-icon" style="background:#D1FAE5">🚀</div>
            <div class="ov-value">{{ number_format($overview['trips_total']) }}</div>
            <div class="ov-label">Trajets total</div>
            <div class="ov-delta up">+{{ number_format($overview['trips_period']) }} ces {{ $period }}j</div>
        </div>
        <div class="ov-card">
            <div class="ov-icon" style="background:#EDE9FE">💳</div>
            <div class="ov-value">{{ number_format($overview['bookings_total']) }}</div>
            <div class="ov-label">Réservations total</div>
            <div class="ov-delta up">+{{ number_format($overview['bookings_period']) }} ces {{ $period }}j</div>
        </div>
        <div class="ov-card">
            <div class="ov-icon" style="background:#D1FAE5">💰</div>
            <div class="ov-value" style="font-size:18px">{{ number_format($overview['revenue_total']) }} F</div>
            <div class="ov-label">Revenus total</div>
            <div class="ov-delta up">+{{ number_format($overview['revenue_period']) }} F ces {{ $period }}j</div>
        </div>
    </div>

    {{-- Alertes --}}
    @if($overview['open_disputes'] > 0 || $overview['open_tickets'] > 0)
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px">
        @if($overview['open_disputes'] > 0)
        <div class="alert-kpi">⚖️ <strong>{{ $overview['open_disputes'] }} litige(s)</strong> ouverts nécessitent une action</div>
        @endif
        @if($overview['open_tickets'] > 0)
        <div class="alert-kpi">🎧 <strong>{{ $overview['open_tickets'] }} ticket(s)</strong> support en attente</div>
        @endif
    </div>
    @endif

    {{-- Revenue chart + Top cities --}}
    <div class="row-2col">
        {{-- Revenue bar chart --}}
        <div class="chart-card" style="grid-column:span 1">
            <h3>Revenus par jour ({{ $period }}j)</h3>
            @if($revenueByDay->isEmpty())
                <div style="text-align:center;padding:40px;color:#9CA3AF">Aucune donnée</div>
            @else
                @php $maxRev = $revenueByDay->max('total') ?: 1; @endphp
                <div class="rev-chart">
                    @foreach($revenueByDay as $rd)
                    <div class="rev-row">
                        <span class="rev-day">{{ \Carbon\Carbon::parse($rd->day)->format('d/m') }}</span>
                        <div class="rev-bar-track">
                            <div class="rev-bar-fill" style="width:{{ ($rd->total / $maxRev) * 100 }}%"></div>
                        </div>
                        <span class="rev-val">{{ number_format($rd->total) }} F</span>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Top cities --}}
        <div class="chart-card">
            <h3>Top villes de départ</h3>
            @if($topCities->isEmpty())
                <div style="text-align:center;padding:40px;color:#9CA3AF">Aucune donnée</div>
            @else
                @php $maxCity = $topCities->max('cnt') ?: 1; @endphp
                @foreach($topCities as $city)
                <div class="bar-item">
                    <span class="bar-label">{{ $city->departure_city }}</span>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:{{ ($city->cnt / $maxCity) * 100 }}%"></div>
                    </div>
                    <span class="bar-count">{{ $city->cnt }}</span>
                </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Trips by status + Payments by method + Top drivers --}}
    <div class="row-3col">
        {{-- Statuts trajets --}}
        <div class="chart-card">
            <h3>Répartition des trajets</h3>
            @php
                $stColors = ['active'=>'#10B981','completed'=>'#1A5FB4','cancelled'=>'#EF4444','pending'=>'#F59E0B'];
                $stNames  = ['active'=>'Actifs','completed'=>'Terminés','cancelled'=>'Annulés','pending'=>'En attente'];
                $totalTrips = $tripsByStatus->sum('cnt') ?: 1;
            @endphp
            <div class="donut-legend">
                @foreach($stNames as $key => $name)
                    @php $cnt = $tripsByStatus[$key]?->cnt ?? 0; @endphp
                    <div class="legend-item">
                        <div class="legend-dot" style="background:{{ $stColors[$key] ?? '#9CA3AF' }}"></div>
                        <span style="flex:1">{{ $name }}</span>
                        <span style="font-weight:700">{{ $cnt }}</span>
                        <span style="color:#9CA3AF;font-size:11px;margin-left:6px">({{ round($cnt/$totalTrips*100) }}%)</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Paiements par opérateur --}}
        <div class="chart-card">
            <h3>Paiements par opérateur</h3>
            @if($payByMethod->isEmpty())
                <div style="text-align:center;padding:20px;color:#9CA3AF">Aucune donnée</div>
            @else
                @php
                    $maxPay = $payByMethod->max('cnt') ?: 1;
                    $payColors = ['mtn'=>'#D97706','moov'=>'#2563EB','celtiis'=>'#7C3AED','fedapay'=>'#10B981'];
                @endphp
                @foreach($payByMethod as $pm)
                <div class="bar-item">
                    <span class="bar-label" style="color:{{ $payColors[$pm->provider??''] ?? '#374151' }}">
                        {{ strtoupper($pm->provider ?? '—') }}
                    </span>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:{{ ($pm->cnt/$maxPay)*100 }}%;background:{{ $payColors[$pm->provider??''] ?? '#1A5FB4' }}"></div>
                    </div>
                    <span class="bar-count">{{ $pm->cnt }}</span>
                </div>
                <div style="font-size:10px;color:#9CA3AF;margin-bottom:8px;margin-left:130px">{{ number_format($pm->total) }} F</div>
                @endforeach
            @endif
        </div>

        {{-- Top drivers --}}
        <div class="chart-card">
            <h3>Top conducteurs ({{ $period }}j)</h3>
            @php $rankClasses = ['gold','silver','bronze','','','','','']; @endphp
            @if($topDrivers->isEmpty())
                <div style="text-align:center;padding:20px;color:#9CA3AF">Aucune donnée</div>
            @else
                @foreach($topDrivers as $i => $td)
                    @php
                        $colors2 = ['#1A5FB4','#10B981','#F59E0B','#EF4444','#6366F1','#8B5CF6'];
                        $tdp = $td->user?->profile;
                        $tdNm = trim(($tdp?->first_name??'').(' '.($tdp?->last_name??''))) ?: ($td->user?->phone??'—');
                        $tdIn = strtoupper(substr($tdp?->first_name??'D',0,1).substr($tdp?->last_name??'',0,1));
                        $tdBg = $colors2[abs(crc32($tdNm))%count($colors2)];
                    @endphp
                    <div class="top-driver-item">
                        <div class="top-rank {{ $rankClasses[$i] ?? '' }}">#{{ $i+1 }}</div>
                        <div class="top-avatar" style="background:{{ $tdBg }}">{{ $tdIn }}</div>
                        <div>
                            <div class="top-name">{{ $tdNm }}</div>
                            <div class="top-sub">{{ $td->user?->phone }}</div>
                        </div>
                        <div class="top-count">{{ $td->trips_count }}</div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Summary KPIs --}}
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-value" style="color:#1A5FB4">{{ number_format($overview['commission_period']) }} F</div>
            <div class="kpi-label">Commission ({{ $period }}j)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value" style="color:#D97706">{{ number_format($overview['disputes_period']) }}</div>
            <div class="kpi-label">Litiges ouverts ({{ $period }}j)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value" style="color:#6B7280">{{ number_format($overview['tickets_period']) }}</div>
            <div class="kpi-label">Tickets support ({{ $period }}j)</div>
        </div>
    </div>

</div>
</div>
