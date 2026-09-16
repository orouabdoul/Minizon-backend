<div>
<style>
.audit-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.audit-header{margin-bottom:24px}
.audit-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.audit-header p{font-size:13px;color:#6B7280;margin:0}

.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;gap:12px}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-value{font-size:22px;font-weight:700;color:#111827}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px}

.filter-bar{background:#fff;border-radius:12px;padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:18px}
.filter-input{flex:1;min-width:200px;padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none}
.filter-input:focus{border-color:#1A5FB4}
.filter-select{padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;background:#fff;cursor:pointer}
.filter-select:focus{border-color:#1A5FB4}
.filter-date{padding:8px 10px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none}

.table-wrap{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);overflow:hidden}
.table-head{padding:14px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between}
.table-head h2{font-size:14px;font-weight:600;color:#374151;margin:0}
.table-count{font-size:12px;color:#9CA3AF}
.data-table{width:100%;border-collapse:collapse}
.data-table th{padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #F3F4F6;background:#FAFAFA}
.data-table td{padding:12px 16px;font-size:13px;color:#374151;border-bottom:1px solid #F9FAFB;vertical-align:middle}
.data-table tr:hover td{background:#F9FAFB}
.data-table tr:last-child td{border-bottom:none}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}

.ip-code{font-family:monospace;font-size:11px;background:#F3F4F6;padding:2px 6px;border-radius:4px;color:#374151}
.action-code{font-family:monospace;font-size:11px;color:#6366F1}

.btn-view{width:28px;height:28px;border-radius:6px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:all .15s}
.btn-view:hover{border-color:#1A5FB4;background:#EFF6FF}

.empty-state{padding:60px;text-align:center;color:#9CA3AF}
.empty-state .icon{font-size:40px;margin-bottom:12px}

/* ── Slide-over panel ───────── */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:520px;max-width:95vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-head h2{font-size:16px;font-weight:700;color:#111827;margin:0}
.panel-close{width:32px;height:32px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:#6B7280;transition:all .15s}
.panel-close:hover{background:#FEE2E2;border-color:#EF4444;color:#EF4444}
.panel-body{flex:1;overflow-y:auto;padding:24px}
.panel-section{margin-bottom:20px}
.panel-section__title{font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px}
.panel-card{background:#F9FAFB;border-radius:10px;padding:14px 16px}
.info-row{display:flex;align-items:flex-start;gap:12px;padding:8px 0;border-bottom:1px solid #F3F4F6}
.info-row:last-child{border-bottom:none}
.info-label{font-size:11px;color:#9CA3AF;min-width:110px;flex-shrink:0;padding-top:1px}
.info-value{font-size:13px;color:#374151;font-weight:500;word-break:break-all}
.payload-box{background:#1F2937;color:#D1FAE5;border-radius:8px;padding:14px;font-family:monospace;font-size:11px;line-height:1.6;overflow-x:auto;white-space:pre-wrap;word-break:break-word;max-height:300px;overflow-y:auto}
</style>

<div class="audit-wrap">

    {{-- Header --}}
    <div class="audit-header">
        <h1>Journal d'Audit</h1>
        <p>Traçabilité complète des actions et événements de la plateforme</p>
    </div>

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:#EFF6FF">📋</div>
            <div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-label">Total événements</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEE2E2">🚨</div>
            <div>
                <div class="stat-value" style="{{ $stats['critical'] > 0 ? 'color:#EF4444' : '' }}">{{ number_format($stats['critical']) }}</div>
                <div class="stat-label">Critiques</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEF3C7">⚠️</div>
            <div>
                <div class="stat-value" style="{{ $stats['warning'] > 0 ? 'color:#D97706' : '' }}">{{ number_format($stats['warning']) }}</div>
                <div class="stat-label">Avertissements</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#D1FAE5">📅</div>
            <div>
                <div class="stat-value">{{ number_format($stats['today']) }}</div>
                <div class="stat-label">Aujourd'hui</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-bar">
        <input type="text" class="filter-input" placeholder="🔍  Rechercher action, IP, description…"
               wire:model.live.debounce.300ms="search">
        <select class="filter-select" wire:model.live="severityFilter">
            <option value="">Toutes sévérités</option>
            <option value="info">Info</option>
            <option value="warning">Avertissement</option>
            <option value="critical">Critique</option>
        </select>
        @if($actionTypes->isNotEmpty())
        <select class="filter-select" wire:model.live="typeFilter">
            <option value="">Tous types</option>
            @foreach($actionTypes as $at)
                <option value="{{ $at }}">{{ $at }}</option>
            @endforeach
        </select>
        @endif
        <input type="date" class="filter-date" wire:model.live="dateFrom" title="Depuis">
        <input type="date" class="filter-date" wire:model.live="dateTo" title="Jusqu'au">
    </div>

    {{-- Table --}}
    <div class="table-wrap">
        <div class="table-head">
            <h2>Événements</h2>
            <span class="table-count">{{ $logs->total() }} entrée(s) — journal immuable</span>
        </div>

        @if($logs->isEmpty())
        <div class="empty-state">
            <div class="icon">📋</div>
            <p>Aucun événement enregistré</p>
        </div>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date / Heure</th>
                    <th>Sévérité</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Acteur</th>
                    <th>IP</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($logs as $log)
            @php
                $sevData = [
                    'info'     => ['label'=>'Info',    'bg'=>'#EFF6FF','color'=>'#1A5FB4'],
                    'warning'  => ['label'=>'Alerte',  'bg'=>'#FEF3C7','color'=>'#D97706'],
                    'critical' => ['label'=>'Critique','bg'=>'#FEE2E2','color'=>'#991B1B'],
                ];
                $sd = $sevData[$log->severity] ?? ['label'=>$log->severity,'bg'=>'#F3F4F6','color'=>'#374151'];
                $actor = $log->user;
                $actorName = $actor
                    ? (trim(($actor->profile?->first_name??'').(' '.($actor->profile?->last_name??''))) ?: $actor->phone)
                    : 'Système';
            @endphp
            <tr>
                <td>
                    <div style="font-size:12px;font-weight:600;color:#374151">{{ $log->created_at?->format('d/m/Y') }}</div>
                    <div style="font-size:11px;color:#9CA3AF">{{ $log->created_at?->format('H:i:s') }}</div>
                </td>
                <td><span class="badge" style="background:{{ $sd['bg'] }};color:{{ $sd['color'] }}">{{ $sd['label'] }}</span></td>
                <td>
                    <div class="action-code">{{ $log->action_type ?? $log->action }}</div>
                    @if($log->action_type && $log->action !== $log->action_type)
                    <div style="font-size:10px;color:#9CA3AF">{{ $log->action }}</div>
                    @endif
                </td>
                <td style="max-width:220px">
                    <div style="font-size:12px;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        {{ $log->description ?? '—' }}
                    </div>
                    @if($log->target_name)
                    <div style="font-size:11px;color:#9CA3AF">→ {{ $log->target_name }}</div>
                    @endif
                </td>
                <td>
                    <div style="font-size:12px;font-weight:500;color:#374151">{{ $actorName }}</div>
                </td>
                <td><span class="ip-code">{{ $log->ip_address }}</span></td>
                <td>
                    <button class="btn-view" wire:click="view({{ $log->id }})" title="Voir le détail">👁</button>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>

        <div style="padding:12px 20px;border-top:1px solid #F3F4F6">
            {{ $logs->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Slide-over panel --}}
@if($selectedLog)
@php
    $sl = $selectedLog;
    $sevData2 = [
        'info'     => ['label'=>'Info',    'bg'=>'#EFF6FF','color'=>'#1A5FB4'],
        'warning'  => ['label'=>'Alerte',  'bg'=>'#FEF3C7','color'=>'#D97706'],
        'critical' => ['label'=>'Critique','bg'=>'#FEE2E2','color'=>'#991B1B'],
    ];
    $sd2 = $sevData2[$sl->severity] ?? ['label'=>$sl->severity,'bg'=>'#F3F4F6','color'=>'#374151'];
    $actor2 = $sl->user;
    $actorName2 = $actor2
        ? (trim(($actor2->profile?->first_name??'').(' '.($actor2->profile?->last_name??''))) ?: $actor2->phone)
        : 'Système';
@endphp
<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    <div class="panel-head">
        <h2>Détail de l'événement</h2>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>
    <div class="panel-body">

        {{-- Header badge --}}
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap">
            <span class="badge" style="background:{{ $sd2['bg'] }};color:{{ $sd2['color'] }};font-size:13px;padding:5px 12px">
                {{ $sd2['label'] }}
            </span>
            <span style="font-family:monospace;font-size:13px;color:#6366F1;font-weight:600">{{ $sl->action }}</span>
        </div>

        {{-- Infos --}}
        <div class="panel-section">
            <div class="panel-section__title">Informations</div>
            <div class="panel-card">
                <div class="info-row">
                    <div class="info-label">Date</div>
                    <div class="info-value">{{ $sl->created_at?->format('d/m/Y à H:i:s') ?? '—' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Type</div>
                    <div class="info-value">{{ $sl->action_type ?? '—' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Description</div>
                    <div class="info-value">{{ $sl->description ?? '—' }}</div>
                </div>
                @if($sl->target_type || $sl->target_name)
                <div class="info-row">
                    <div class="info-label">Cible</div>
                    <div class="info-value">{{ $sl->target_type ?? '' }} — {{ $sl->target_name ?? '—' }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Acteur --}}
        <div class="panel-section">
            <div class="panel-section__title">Acteur</div>
            <div class="panel-card">
                <div class="info-row">
                    <div class="info-label">Utilisateur</div>
                    <div class="info-value">{{ $actorName2 }}</div>
                </div>
                @if($actor2)
                <div class="info-row">
                    <div class="info-label">Téléphone</div>
                    <div class="info-value" style="font-family:monospace">{{ $actor2->phone }}</div>
                </div>
                @endif
                <div class="info-row">
                    <div class="info-label">Adresse IP</div>
                    <div class="info-value"><span class="ip-code">{{ $sl->ip_address }}</span></div>
                </div>
                @if($sl->user_agent)
                <div class="info-row">
                    <div class="info-label">User Agent</div>
                    <div class="info-value" style="font-size:11px;color:#9CA3AF;word-break:break-all">{{ $sl->user_agent }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Payload --}}
        @if($sl->payload)
        <div class="panel-section">
            <div class="panel-section__title">Données contextuelles (payload)</div>
            <div class="payload-box">{{ json_encode($sl->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</div>
        </div>
        @endif

    </div>
</div>
@endif
</div>
