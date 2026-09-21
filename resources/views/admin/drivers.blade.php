<div>
<style>
.page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; }
.page-header__title { font-size:22px; font-weight:700; color:#1F2933; }
.page-header__sub   { font-size:13px; color:#6B7684; margin-top:2px; }

/* Stats */
.stat-bar { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
@media(max-width:900px){ .stat-bar { grid-template-columns:repeat(2,1fr); } }
.stat-mini { background:#fff; border-radius:12px; border:1px solid #F3F4F6; padding:14px 16px; display:flex; align-items:center; gap:12px; }
.stat-mini__icon { width:38px; height:38px; border-radius:9px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.stat-mini__val  { font-size:20px; font-weight:700; color:#1F2933; line-height:1; }
.stat-mini__lbl  { font-size:11px; color:#9CA3AF; margin-top:2px; }
.stat-mini--alert { border-color:#FEE2E2; background:#FFFAFA; }
.stat-mini--warn  { border-color:#FEF3C7; background:#FFFDF0; }

/* Filtres */
.filter-bar { background:#fff; border:1px solid #F3F4F6; border-radius:12px; padding:14px 16px; margin-bottom:16px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
.filter-search { display:flex; align-items:center; gap:8px; background:#F9FAFB; border:1px solid #E5E7EB; border-radius:8px; padding:8px 12px; flex:1; min-width:220px; }
.filter-search input { border:none; background:transparent; outline:none; font-family:inherit; font-size:13px; color:#1F2933; width:100%; }
.filter-search input::placeholder { color:#9CA3AF; }
.filter-select { border:1px solid #E5E7EB; border-radius:8px; padding:8px 12px; font-size:13px; color:#374151; background:#F9FAFB; cursor:pointer; font-family:inherit; outline:none; }

/* Table */
.data-table-wrap { background:#fff; border-radius:14px; border:1px solid #F3F4F6; overflow:hidden; }
.data-table { width:100%; border-collapse:collapse; }
.data-table thead th { padding:11px 16px; text-align:left; font-size:11px; font-weight:600; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.6px; background:#FAFAFA; border-bottom:1px solid #F3F4F6; white-space:nowrap; }
.data-table tbody td { padding:13px 16px; border-bottom:1px solid #F9FAFB; font-size:13px; color:#1F2933; vertical-align:middle; }
.data-table tbody tr:last-child td { border-bottom:none; }
.data-table tbody tr:hover td { background:#FAFBFF; }

/* Avatar */
.user-avatar { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex-shrink:0; background:#DBEAFE; color:#1A5FB4; }
.user-info { display:flex; align-items:center; gap:10px; }
.user-info__name  { font-size:13px; font-weight:600; color:#1F2933; }
.user-info__phone { font-size:11px; color:#9CA3AF; margin-top:1px; }

/* Badges */
.badge { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap; }
.badge-approved  { background:rgba(22,163,74,0.10);  color:#16A34A; }
.badge-pending   { background:rgba(217,119,6,0.10);  color:#D97706; }
.badge-rejected  { background:rgba(220,38,38,0.10);  color:#DC2626; }
.badge-none      { background:#F3F4F6; color:#9CA3AF; }

/* KYC urgency pulse */
.badge-pending-pulse { animation: kycpulse 2s ease-in-out infinite; }
@keyframes kycpulse { 0%,100%{ box-shadow:0 0 0 0 rgba(217,119,6,0.3); } 50%{ box-shadow:0 0 0 4px rgba(217,119,6,0); } }

/* Véhicule */
.vehicle-info { }
.vehicle-info__name  { font-size:12px; font-weight:600; color:#374151; }
.vehicle-info__plate { font-size:11px; color:#9CA3AF; margin-top:1px; font-family:monospace; letter-spacing:0.5px; }
.vehicle-info__seats { font-size:10px; color:#6B7684; margin-top:1px; }

/* Docs checklist */
.doc-checks { display:flex; gap:4px; flex-wrap:wrap; margin-top:4px; }
.doc-chip { font-size:9px; font-weight:600; padding:2px 6px; border-radius:4px; }
.doc-chip--ok  { background:#DCFCE7; color:#15803D; }
.doc-chip--no  { background:#FEE2E2; color:#B91C1C; }

/* Actions */
.action-btns { display:flex; gap:6px; flex-wrap:wrap; }
.action-btn { height:30px; padding:0 10px; border-radius:7px; border:1px solid #E5E7EB; background:#fff; cursor:pointer; display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:600; font-family:inherit; transition:all .15s; color:#374151; white-space:nowrap; }
.action-btn--approve { border-color:#BBF7D0; color:#16A34A; background:#F0FDF4; }
.action-btn--approve:hover { background:#16A34A; color:#fff; border-color:#16A34A; }
.action-btn--reject  { border-color:#FECACA; color:#DC2626; background:#FEF2F2; }
.action-btn--reject:hover  { background:#DC2626; color:#fff; border-color:#DC2626; }

/* Empty */
.empty-state { padding:60px 20px; text-align:center; }
.empty-state__icon  { width:56px; height:56px; border-radius:14px; background:#F3F4F6; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
.empty-state__title { font-size:15px; font-weight:600; color:#1F2933; margin-bottom:4px; }
.empty-state__sub   { font-size:13px; color:#9CA3AF; }

/* Footer */
.table-footer { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-top:1px solid #F3F4F6; background:#FAFAFA; flex-wrap:wrap; gap:8px; }
.table-footer__info { font-size:12px; color:#9CA3AF; }
</style>

{{-- Header --}}
<div class="page-header">
    <div>
        <div class="page-header__title">Conducteurs</div>
        <div class="page-header__sub">Validation KYC, véhicules et gestion des conducteurs</div>
    </div>
    @if($stats['pending'] > 0)
    <div style="display:flex;align-items:center;gap:8px;background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:10px 14px;">
        <span style="width:8px;height:8px;border-radius:50%;background:#D97706;display:inline-block;animation:kycpulse 2s infinite"></span>
        <span style="font-size:13px;font-weight:600;color:#92400E">{{ $stats['pending'] }} dossier(s) KYC en attente</span>
    </div>
    @endif
</div>

{{-- Stats --}}
<div class="stat-bar">
    <div class="stat-mini">
        <div class="stat-mini__icon" style="background:rgba(26,95,180,0.10)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1A5FB4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
        </div>
        <div><div class="stat-mini__val">{{ $stats['total'] }}</div><div class="stat-mini__lbl">Total conducteurs</div></div>
    </div>
    <div class="stat-mini {{ $stats['pending'] > 0 ? 'stat-mini--warn' : '' }}">
        <div class="stat-mini__icon" style="background:rgba(217,119,6,0.10)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
        </div>
        <div><div class="stat-mini__val" style="color:#D97706">{{ $stats['pending'] }}</div><div class="stat-mini__lbl">KYC en attente</div></div>
    </div>
    <div class="stat-mini">
        <div class="stat-mini__icon" style="background:rgba(22,163,74,0.10)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <div><div class="stat-mini__val" style="color:#16A34A">{{ $stats['approved'] }}</div><div class="stat-mini__lbl">KYC approuvés</div></div>
    </div>
    <div class="stat-mini {{ $stats['rejected'] > 0 ? 'stat-mini--alert' : '' }}">
        <div class="stat-mini__icon" style="background:rgba(220,38,38,0.10)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
        </div>
        <div><div class="stat-mini__val" style="color:#DC2626">{{ $stats['rejected'] }}</div><div class="stat-mini__lbl">KYC rejetés</div></div>
    </div>
</div>

{{-- Filtres --}}
<div class="filter-bar">
    <div class="filter-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher par nom ou téléphone…">
    </div>
    <select class="filter-select" wire:model.live="kycFilter">
        <option value="">KYC — Tous</option>
        <option value="pending">En attente</option>
        <option value="approved">Approuvé</option>
        <option value="rejected">Rejeté</option>
    </select>
    <select class="filter-select" wire:model.live="vehicleFilter">
        <option value="">Véhicule — Tous</option>
        <option value="pending">En attente</option>
        <option value="approved">Approuvé</option>
        <option value="rejected">Rejeté</option>
    </select>
</div>

{{-- Table --}}
<div class="data-table-wrap">
    @if($drivers->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>Conducteur</th>
                <th>KYC</th>
                <th>Véhicule</th>
                <th>Statut véhicule</th>
                <th>Documents</th>
                <th>Inscription</th>
                <th>Actions KYC</th>
            </tr>
        </thead>
        <tbody>
            @foreach($drivers as $driver)
            @php
                $name     = $driver->profile ? trim(($driver->profile->first_name ?? '') . ' ' . ($driver->profile->last_name ?? '')) : '';
                $initials = $name ? strtoupper(substr($driver->profile->first_name ?? '?', 0, 1) . substr($driver->profile->last_name ?? '', 0, 1)) : '??';
                $kyc      = $driver->profile?->kyc_status;
                $veh      = $driver->vehicle;
                $vStatus  = $veh?->verification_status ?? null;
                $kycMap   = [
                    'approved' => ['label'=>'Approuvé',   'class'=>'badge-approved'],
                    'pending'  => ['label'=>'En attente', 'class'=>'badge-pending badge-pending-pulse'],
                    'rejected' => ['label'=>'Rejeté',     'class'=>'badge-rejected'],
                ];
                $vMap = [
                    'approved' => ['label'=>'Approuvé', 'class'=>'badge-approved'],
                    'pending'  => ['label'=>'En attente','class'=>'badge-pending'],
                    'rejected' => ['label'=>'Rejeté',   'class'=>'badge-rejected'],
                    'suspended'=> ['label'=>'Suspendu', 'class'=>'badge-rejected'],
                ];
            @endphp
            <tr>
                {{-- Conducteur --}}
                <td>
                    <div class="user-info">
                        <div class="user-avatar">{{ $initials }}</div>
                        <div>
                            <div class="user-info__name">{{ $name ?: '— Sans profil —' }}</div>
                            <div class="user-info__phone">{{ $driver->phone }}</div>
                        </div>
                    </div>
                </td>

                {{-- KYC --}}
                <td>
                    @if($kyc && isset($kycMap[$kyc]))
                        <span class="badge {{ $kycMap[$kyc]['class'] }}">{{ $kycMap[$kyc]['label'] }}</span>
                    @else
                        <span class="badge badge-none">Non soumis</span>
                    @endif
                </td>

                {{-- Véhicule --}}
                <td>
                    @if($veh)
                    <div class="vehicle-info">
                        <div class="vehicle-info__name">{{ $veh->brand }} {{ $veh->model }}</div>
                        <div class="vehicle-info__plate">{{ $veh->license_plate ?? '—' }}</div>
                        <div class="vehicle-info__seats">{{ $veh->available_seats ?? '?' }} places · {{ $veh->color ?? '' }}</div>
                    </div>
                    @else
                        <span style="font-size:12px;color:#9CA3AF">Aucun véhicule</span>
                    @endif
                </td>

                {{-- Statut véhicule --}}
                <td>
                    @if($vStatus && isset($vMap[$vStatus]))
                        <span class="badge {{ $vMap[$vStatus]['class'] }}">{{ $vMap[$vStatus]['label'] }}</span>
                    @else
                        <span class="badge badge-none">—</span>
                    @endif
                </td>

                {{-- Documents --}}
                <td>
                    @if($veh)
                    <div class="doc-checks">
                        <span class="doc-chip {{ $veh->registration_doc     ? 'doc-chip--ok' : 'doc-chip--no' }}">Carte grise</span>
                        <span class="doc-chip {{ $veh->insurance_doc         ? 'doc-chip--ok' : 'doc-chip--no' }}">Assurance</span>
                        <span class="doc-chip {{ $veh->tvm_doc               ? 'doc-chip--ok' : 'doc-chip--no' }}">TVM</span>
                        <span class="doc-chip {{ $veh->technical_control_doc ? 'doc-chip--ok' : 'doc-chip--no' }}">Contrôle</span>
                    </div>
                    @else
                        <span style="font-size:11px;color:#9CA3AF">—</span>
                    @endif
                </td>

                {{-- Date --}}
                <td style="color:#6B7684;font-size:12px;white-space:nowrap">
                    {{ $driver->created_at?->format('d/m/Y') }}
                </td>

                {{-- Actions KYC --}}
                <td>
                    <div class="action-btns" style="flex-direction:column;gap:4px;align-items:flex-start">
                        {{-- KYC conducteur --}}
                        @if($kyc === 'pending' || !$kyc)
                        <button class="action-btn action-btn--approve" wire:click="approveKyc({{ $driver->id }})" wire:confirm="Approuver le KYC de {{ $name ?: $driver->phone }} ?" title="Approuver KYC">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            KYC Approuver
                        </button>
                        <button class="action-btn action-btn--reject" wire:click="rejectKyc({{ $driver->id }})" wire:confirm="Rejeter le KYC de {{ $name ?: $driver->phone }} ?" title="Rejeter KYC">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            KYC Rejeter
                        </button>
                        @elseif($kyc === 'approved')
                        <button class="action-btn action-btn--reject" wire:click="rejectKyc({{ $driver->id }})" wire:confirm="Révoquer l'approbation KYC ?" title="Révoquer KYC">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            KYC Révoquer
                        </button>
                        @elseif($kyc === 'rejected')
                        <button class="action-btn action-btn--approve" wire:click="approveKyc({{ $driver->id }})" wire:confirm="Ré-approuver le KYC ?" title="Ré-approuver KYC">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            KYC Ré-approuver
                        </button>
                        @endif
                        {{-- Véhicule --}}
                        @if($veh && ($vStatus === 'pending' || !$vStatus))
                        <button class="action-btn action-btn--approve" wire:click="approveVehicle({{ $driver->id }})" wire:confirm="Approuver le véhicule de {{ $name ?: $driver->phone }} ?" title="Approuver véhicule">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Véh. Approuver
                        </button>
                        <button class="action-btn action-btn--reject" wire:click="rejectVehicle({{ $driver->id }})" wire:confirm="Rejeter le véhicule de {{ $name ?: $driver->phone }} ?" title="Rejeter véhicule">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Véh. Rejeter
                        </button>
                        @elseif($veh && $vStatus === 'approved')
                        <button class="action-btn action-btn--reject" wire:click="rejectVehicle({{ $driver->id }})" wire:confirm="Révoquer l'approbation du véhicule ?" title="Révoquer véhicule">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Véh. Révoquer
                        </button>
                        @elseif($veh && $vStatus === 'rejected')
                        <button class="action-btn action-btn--approve" wire:click="approveVehicle({{ $driver->id }})" wire:confirm="Approuver quand même le véhicule ?" title="Ré-approuver véhicule">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Véh. Ré-approuver
                        </button>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="table-footer">
        <span class="table-footer__info">{{ $drivers->firstItem() }}–{{ $drivers->lastItem() }} sur {{ $drivers->total() }} conducteurs</span>
        {{ $drivers->links() }}
    </div>

    @else
    <div class="empty-state">
        <div class="empty-state__icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
        </div>
        <div class="empty-state__title">Aucun conducteur trouvé</div>
        <div class="empty-state__sub">Modifie les filtres pour voir plus de résultats</div>
    </div>
    @endif
</div>

</div>
