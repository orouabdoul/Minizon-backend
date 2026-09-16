@php
$verLabels = [
    'pending'   => ['label' => 'En attente',  'color' => '#F59E0B', 'bg' => '#FEF3C7', 'dot' => '#F59E0B'],
    'approved'  => ['label' => 'Approuvé',    'color' => '#10B981', 'bg' => '#D1FAE5', 'dot' => '#10B981'],
    'rejected'  => ['label' => 'Rejeté',      'color' => '#EF4444', 'bg' => '#FEE2E2', 'dot' => '#EF4444'],
    'suspended' => ['label' => 'Suspendu',    'color' => '#6B7280', 'bg' => '#F3F4F6', 'dot' => '#9CA3AF'],
];
$docLabels = [
    'registration_doc'      => 'Carte grise',
    'insurance_doc'         => 'Assurance',
    'tvm_doc'               => 'TVM',
    'technical_control_doc' => 'Visite technique',
];
$colors = ['#1A5FB4','#FF7A45','#10B981','#6366F1','#F59E0B','#EF4444','#8B5CF6','#06B6D4'];
@endphp

<div>
<style>
.veh-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.veh-header{margin-bottom:24px}
.veh-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.veh-header p{font-size:13px;color:#6B7280;margin:0}

.stat-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:#fff;border-radius:12px;padding:16px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);cursor:pointer;transition:box-shadow .15s,transform .15s;border:2px solid transparent}
.stat-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.1);transform:translateY(-1px)}
.stat-card.active{border-color:#1A5FB4}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-value{font-size:20px;font-weight:700;color:#111827;line-height:1.2}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}

.filter-bar{background:#fff;border-radius:12px;padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
.filter-input{flex:1;min-width:200px;padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;transition:border-color .15s}
.filter-input:focus{border-color:#1A5FB4}
.filter-select{padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;background:#fff;cursor:pointer}
.filter-select:focus{border-color:#1A5FB4}
.btn-reset{padding:9px 16px;background:#F3F4F6;border:1.5px solid #E5E7EB;border-radius:8px;font-size:12px;color:#6B7280;cursor:pointer}
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

.veh-cell{display:flex;align-items:center;gap:10px}
.veh-icon{width:40px;height:40px;border-radius:8px;background:#F3F4F6;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;overflow:hidden}
.veh-icon img{width:100%;height:100%;object-fit:cover;border-radius:8px}
.user-cell{display:flex;align-items:center;gap:8px}
.avatar{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600}
.doc-check{display:inline-flex;align-items:center;gap:3px;font-size:10px;padding:2px 6px;border-radius:4px;font-weight:600}
.action-group{display:flex;gap:5px}
.btn-action{width:30px;height:30px;border-radius:7px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s}
.btn-action:hover{border-color:#1A5FB4;background:#EFF6FF}

.empty-state{padding:60px;text-align:center;color:#9CA3AF}
.empty-state .icon{font-size:40px;margin-bottom:12px}

/* Panel */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:520px;max-width:95vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-head h2{font-size:16px;font-weight:700;color:#111827;margin:0}
.panel-close{width:32px;height:32px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;font-size:16px;color:#6B7280;transition:all .15s;display:flex;align-items:center;justify-content:center}
.panel-close:hover{background:#FEE2E2;border-color:#EF4444;color:#EF4444}
.panel-body{flex:1;overflow-y:auto}
.panel-hero{padding:24px;background:#FAFAFA;border-bottom:1px solid #F3F4F6}
.panel-photo{width:100%;height:160px;border-radius:10px;object-fit:cover;background:#E5E7EB;display:flex;align-items:center;justify-content:center;font-size:48px;margin-bottom:16px;overflow:hidden}
.panel-content{padding:20px 24px}
.panel-section{margin-bottom:22px}
.panel-section__title{font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px}
.panel-card{background:#F9FAFB;border-radius:10px;padding:14px 16px}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #F3F4F6}
.info-row:last-child{border-bottom:none}
.info-row label{font-size:12px;color:#6B7280}
.info-row span{font-size:13px;font-weight:500;color:#374151;text-align:right}
.doc-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.doc-item{background:#F9FAFB;border-radius:8px;padding:10px 12px;display:flex;align-items:center;gap:8px;border:1.5px solid #E5E7EB;cursor:pointer;text-decoration:none;transition:border-color .15s}
.doc-item:hover{border-color:#1A5FB4;background:#EFF6FF}
.doc-item-name{font-size:11px;font-weight:600;color:#374151}
.doc-item-sub{font-size:10px;color:#9CA3AF}
.reject-form{background:#FFF5F5;border-radius:10px;padding:14px;border:1.5px solid #FECACA;margin-top:12px}
.reject-textarea{width:100%;padding:10px;border:1.5px solid #FECACA;border-radius:8px;font-size:13px;resize:vertical;outline:none;box-sizing:border-box;font-family:inherit}
.reject-textarea:focus{border-color:#EF4444}
.panel-footer{padding:16px 24px;border-top:1px solid #F3F4F6;display:flex;flex-direction:column;gap:8px;flex-shrink:0}
.btn-approve{padding:10px;background:#10B981;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:.15s}
.btn-approve:hover{background:#059669}
.btn-reject{padding:10px;background:#FEE2E2;color:#EF4444;border:1.5px solid #FECACA;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:.15s}
.btn-reject:hover{background:#EF4444;color:#fff;border-color:#EF4444}
.btn-suspend{padding:10px;background:#F3F4F6;color:#6B7280;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:.15s}
.btn-suspend:hover{background:#6B7280;color:#fff}
.btn-close{padding:10px;background:#F3F4F6;color:#6B7280;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.panel-btn-row{display:flex;gap:8px}
</style>

<div class="veh-wrap">

    <div class="veh-header">
        <h1>Véhicules</h1>
        <p>Vérification et gestion des véhicules conducteurs</p>
    </div>

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card {{ $statusFilter==='' ? 'active' : '' }}" wire:click="$set('statusFilter','')">
            <div class="stat-icon" style="background:#EFF6FF">🚗</div>
            <div><div class="stat-value">{{ number_format($stats['total']) }}</div><div class="stat-label">Total</div></div>
        </div>
        <div class="stat-card {{ $statusFilter==='pending' ? 'active' : '' }}" wire:click="$set('statusFilter','pending')">
            <div class="stat-icon" style="background:#FEF3C7">⏳</div>
            <div>
                <div class="stat-value" style="{{ $stats['pending']>0?'color:#D97706':'' }}">{{ number_format($stats['pending']) }}</div>
                <div class="stat-label">En attente</div>
            </div>
        </div>
        <div class="stat-card {{ $statusFilter==='approved' ? 'active' : '' }}" wire:click="$set('statusFilter','approved')">
            <div class="stat-icon" style="background:#D1FAE5">✅</div>
            <div><div class="stat-value" style="color:#10B981">{{ number_format($stats['approved']) }}</div><div class="stat-label">Approuvés</div></div>
        </div>
        <div class="stat-card {{ $statusFilter==='rejected' ? 'active' : '' }}" wire:click="$set('statusFilter','rejected')">
            <div class="stat-icon" style="background:#FEE2E2">❌</div>
            <div><div class="stat-value" style="{{ $stats['rejected']>0?'color:#EF4444':'' }}">{{ number_format($stats['rejected']) }}</div><div class="stat-label">Rejetés</div></div>
        </div>
        <div class="stat-card {{ $statusFilter==='suspended' ? 'active' : '' }}" wire:click="$set('statusFilter','suspended')">
            <div class="stat-icon" style="background:#F3F4F6">⛔</div>
            <div><div class="stat-value" style="color:#6B7280">{{ number_format($stats['suspended']) }}</div><div class="stat-label">Suspendus</div></div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-bar">
        <input class="filter-input" type="text" placeholder="Rechercher marque, immatriculation, conducteur…"
               wire:model.live.debounce.400ms="search">
        <select class="filter-select" wire:model.live="statusFilter">
            <option value="">Tous les statuts</option>
            @foreach($verLabels as $key => $v)
                <option value="{{ $key }}">{{ $v['label'] }}</option>
            @endforeach
        </select>
        <select class="filter-select" wire:model.live="typeFilter">
            <option value="">Tous les types</option>
            @foreach($types as $type)
                <option value="{{ $type->id }}">{{ $type->name }}</option>
            @endforeach
        </select>
        <button class="btn-reset" wire:click="resetFilters">Réinitialiser</button>
    </div>

    {{-- Table --}}
    <div class="table-wrap">
        <div class="table-head">
            <h2>Liste des véhicules</h2>
            <span class="table-count">{{ $vehicles->total() }} véhicule(s)</span>
        </div>

        @if($vehicles->isEmpty())
            <div class="empty-state"><div class="icon">🚗</div><div>Aucun véhicule trouvé</div></div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Véhicule</th>
                        <th>Type</th>
                        <th>Immatriculation</th>
                        <th>Conducteur</th>
                        <th>Places</th>
                        <th>Documents</th>
                        <th>Statut</th>
                        <th>Ajouté le</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($vehicles as $veh)
                    @php
                        $vl   = $verLabels[$veh->verification_status ?? 'pending'] ?? $verLabels['pending'];
                        $drv  = $veh->user;
                        $dp   = $drv?->profile;
                        $dNm  = trim(($dp?->first_name??'').(' '.($dp?->last_name??''))) ?: ($drv?->phone??'—');
                        $dIn  = strtoupper(substr($dp?->first_name??'D',0,1).substr($dp?->last_name??'',0,1));
                        $dBg  = $colors[abs(crc32($dNm))%count($colors)];
                        $photo = $veh->vehicle_photo ? Storage::disk('public')->url($veh->vehicle_photo) : null;
                        $docsOk = collect(['registration_doc','insurance_doc','tvm_doc','technical_control_doc'])
                            ->filter(fn($d) => !empty($veh->$d))->count();
                    @endphp
                    <tr class="{{ ($veh->verification_status??'pending')==='pending' ? 'row-pending' : '' }}">
                        <td>
                            <div class="veh-cell">
                                <div class="veh-icon">
                                    @if($photo)
                                        <img src="{{ $photo }}" alt="{{ $veh->brand }}"
                                             onerror="this.parentElement.innerHTML='🚗'">
                                    @else
                                        🚗
                                    @endif
                                </div>
                                <div>
                                    <div style="font-weight:600;color:#111827;font-size:13px">{{ $veh->brand }} {{ $veh->model }}</div>
                                    <div style="font-size:11px;color:#9CA3AF">{{ $veh->color }}{{ $veh->year ? ' · '.$veh->year : '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:12px">{{ $veh->vehicleType?->name ?? '—' }}</td>
                        <td>
                            <code style="font-size:12px;background:#F3F4F6;padding:3px 8px;border-radius:5px;font-family:monospace">
                                {{ $veh->license_plate }}
                            </code>
                        </td>
                        <td>
                            <div class="user-cell">
                                <div class="avatar" style="background:{{ $dBg }}">{{ $dIn }}</div>
                                <div>
                                    <div style="font-size:12px;font-weight:600;color:#374151">{{ $dNm }}</div>
                                    <div style="font-size:10px;color:#9CA3AF">{{ $drv?->phone }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="font-weight:600;text-align:center">{{ $veh->available_seats }}</td>
                        <td>
                            <span style="font-size:12px;font-weight:600;color:{{ $docsOk === 4 ? '#10B981' : ($docsOk >= 2 ? '#F59E0B' : '#EF4444') }}">
                                {{ $docsOk }}/4
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="color:{{ $vl['color'] }};background:{{ $vl['bg'] }}">
                                {{ $vl['label'] }}
                            </span>
                        </td>
                        <td style="font-size:11px;color:#9CA3AF">{{ $veh->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <button class="btn-action" wire:click="view({{ $veh->id }})">👁️</button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div style="padding:12px 20px;border-top:1px solid #F3F4F6">{{ $vehicles->links() }}</div>
        @endif
    </div>

</div>

{{-- Slide-over panel --}}
@if($selected)
    @php
        $v   = $selected;
        $vls = $verLabels[$v->verification_status ?? 'pending'] ?? $verLabels['pending'];
        $d   = $v->user;
        $dp2 = $d?->profile;
        $dNm2 = trim(($dp2?->first_name??'').(' '.($dp2?->last_name??''))) ?: ($d?->phone??'—');
        $dBg2 = $colors[abs(crc32($dNm2))%count($colors)];
        $dIn2 = strtoupper(substr($dp2?->first_name??'D',0,1).substr($dp2?->last_name??'',0,1));
        $photo2 = $v->vehicle_photo ? Storage::disk('public')->url($v->vehicle_photo) : null;
    @endphp

    <div class="panel-overlay" wire:click="closeView"></div>
    <div class="panel-drawer">
        <div class="panel-head">
            <h2>{{ $v->brand }} {{ $v->model }}</h2>
            <button class="panel-close" wire:click="closeView">✕</button>
        </div>

        <div class="panel-body">
            {{-- Hero photo --}}
            <div class="panel-hero">
                <div class="panel-photo">
                    @if($photo2)
                        <img src="{{ $photo2 }}" alt="{{ $v->brand }}" style="width:100%;height:100%;object-fit:cover"
                             onerror="this.parentElement.innerHTML='🚗'">
                    @else
                        🚗
                    @endif
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <div style="font-size:18px;font-weight:700;color:#111827">{{ $v->brand }} {{ $v->model }}</div>
                        <div style="font-size:13px;color:#6B7280;margin-top:2px">{{ $v->color }}{{ $v->year ? ' · '.$v->year : '' }} · {{ $v->available_seats }} places</div>
                    </div>
                    <span class="badge" style="color:{{ $vls['color'] }};background:{{ $vls['bg'] }};font-size:12px;padding:5px 12px">
                        {{ $vls['label'] }}
                    </span>
                </div>
            </div>

            <div class="panel-content">

                {{-- Infos véhicule --}}
                <div class="panel-section">
                    <div class="panel-section__title">Informations</div>
                    <div class="panel-card">
                        <div class="info-row"><label>Marque</label><span>{{ $v->brand }}</span></div>
                        <div class="info-row"><label>Modèle</label><span>{{ $v->model }}</span></div>
                        <div class="info-row"><label>Couleur</label><span>{{ $v->color }}</span></div>
                        <div class="info-row"><label>Année</label><span>{{ $v->year ?? '—' }}</span></div>
                        <div class="info-row"><label>Immatriculation</label><span><code style="font-size:12px">{{ $v->license_plate }}</code></span></div>
                        <div class="info-row"><label>Type</label><span>{{ $v->vehicleType?->name ?? '—' }}</span></div>
                        <div class="info-row"><label>Places disponibles</label><span>{{ $v->available_seats }}</span></div>
                        @if($v->rejection_reason)
                        <div class="info-row"><label>Motif de rejet</label><span style="color:#EF4444;font-size:12px">{{ $v->rejection_reason }}</span></div>
                        @endif
                    </div>
                </div>

                {{-- Conducteur --}}
                <div class="panel-section">
                    <div class="panel-section__title">Conducteur propriétaire</div>
                    <div class="panel-card">
                        <div style="display:flex;align-items:center;gap:12px;padding-bottom:12px;border-bottom:1px solid #F3F4F6;margin-bottom:8px">
                            <div style="width:44px;height:44px;border-radius:50%;background:{{ $dBg2 }};display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#fff">{{ $dIn2 }}</div>
                            <div>
                                <div style="font-weight:600;color:#111827">{{ $dNm2 }}</div>
                                <div style="font-size:12px;color:#9CA3AF">{{ $d?->phone }}</div>
                            </div>
                        </div>
                        <div class="info-row"><label>Ville</label><span>{{ $dp2?->city ?? '—' }}</span></div>
                        <div class="info-row"><label>Inscrit le</label><span>{{ $d?->created_at?->format('d/m/Y') }}</span></div>
                    </div>
                </div>

                {{-- Documents --}}
                <div class="panel-section">
                    <div class="panel-section__title">Documents</div>
                    <div class="doc-grid">
                        @foreach($docLabels as $field => $label)
                            @php $docUrl = $v->$field ? Storage::disk('public')->url($v->$field) : null; @endphp
                            <a href="{{ $docUrl ?? '#' }}" target="{{ $docUrl ? '_blank' : '' }}"
                               class="doc-item" style="{{ !$docUrl ? 'opacity:.5;cursor:not-allowed' : '' }}">
                                <span style="font-size:20px">{{ $docUrl ? '📄' : '📭' }}</span>
                                <div>
                                    <div class="doc-item-name">{{ $label }}</div>
                                    <div class="doc-item-sub">{{ $docUrl ? 'Disponible' : 'Manquant' }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Historique vérif --}}
                @if($v->verified_at)
                <div class="panel-section">
                    <div class="panel-section__title">Vérification</div>
                    <div class="panel-card">
                        <div class="info-row"><label>Vérifié le</label><span>{{ $v->verified_at->format('d/m/Y H:i') }}</span></div>
                    </div>
                </div>
                @endif

                {{-- Reject form --}}
                @if($showRejectForm)
                <div class="reject-form">
                    <div style="font-size:12px;font-weight:600;color:#EF4444;margin-bottom:8px">Motif du rejet</div>
                    <textarea class="reject-textarea" rows="3" placeholder="Expliquer le motif du rejet…"
                              wire:model="rejectReason"></textarea>
                    <div style="display:flex;gap:8px;margin-top:10px">
                        <button style="flex:1;padding:9px;background:#EF4444;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer"
                                wire:click="reject({{ $v->id }})" wire:confirm="Confirmer le rejet ?">
                            Confirmer le rejet
                        </button>
                        <button style="padding:9px 16px;background:#F3F4F6;border:none;border-radius:8px;font-size:13px;cursor:pointer"
                                wire:click="$set('showRejectForm',false)">Annuler</button>
                    </div>
                </div>
                @endif

            </div>
        </div>

        {{-- Footer actions --}}
        <div class="panel-footer">
            @if(($v->verification_status ?? 'pending') !== 'approved')
                <button class="btn-approve" wire:click="approve({{ $v->id }})" wire:confirm="Approuver ce véhicule ?">
                    ✅ Approuver
                </button>
            @endif
            @if(!$showRejectForm && ($v->verification_status ?? 'pending') !== 'rejected')
                <div class="panel-btn-row">
                    <button class="btn-reject" wire:click="openRejectForm">❌ Rejeter</button>
                    @if(($v->verification_status ?? 'pending') !== 'suspended')
                        <button class="btn-suspend" wire:click="suspend({{ $v->id }})" wire:confirm="Suspendre ce véhicule ?">
                            ⛔ Suspendre
                        </button>
                    @endif
                </div>
            @endif
            <button class="btn-close" wire:click="closeView">Fermer</button>
        </div>
    </div>
@endif

</div>
