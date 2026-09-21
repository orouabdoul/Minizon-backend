<div>
<style>
.page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; }
.page-header__title { font-size:22px; font-weight:700; color:#1F2933; }
.page-header__sub   { font-size:13px; color:#6B7684; margin-top:2px; }

/* Stats */
.stat-bar { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
@media(max-width:900px){ .stat-bar { grid-template-columns:repeat(2,1fr); } }
.stat-mini { background:#fff; border-radius:12px; border:1px solid #F3F4F6; padding:14px 16px; display:flex; align-items:center; gap:12px; cursor:pointer; transition:border-color .15s; }
.stat-mini:hover { border-color:#1A5FB4; }
.stat-mini__icon { width:38px; height:38px; border-radius:9px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.stat-mini__val  { font-size:20px; font-weight:700; color:#1F2933; line-height:1; }
.stat-mini__lbl  { font-size:11px; color:#9CA3AF; margin-top:2px; }

/* Filtres */
.filter-bar { background:#fff; border:1px solid #F3F4F6; border-radius:12px; padding:14px 16px; margin-bottom:16px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
.filter-search { display:flex; align-items:center; gap:8px; background:#F9FAFB; border:1px solid #E5E7EB; border-radius:8px; padding:8px 12px; flex:1; min-width:220px; }
.filter-search input { border:none; background:transparent; outline:none; font-family:inherit; font-size:13px; color:#1F2933; width:100%; }
.filter-search input::placeholder { color:#9CA3AF; }
.filter-select { border:1px solid #E5E7EB; border-radius:8px; padding:8px 12px; font-size:13px; color:#374151; background:#F9FAFB; cursor:pointer; font-family:inherit; outline:none; }

/* Tabs */
.tab-bar { display:flex; gap:4px; background:#F3F4F6; border-radius:10px; padding:4px; margin-bottom:16px; width:fit-content; }
.tab-btn { padding:7px 18px; border-radius:7px; font-size:13px; font-weight:500; border:none; background:transparent; cursor:pointer; color:#6B7684; transition:all .15s; font-family:inherit; }
.tab-btn.active { background:#fff; color:#1A5FB4; font-weight:600; box-shadow:0 1px 4px rgba(0,0,0,0.08); }

/* Table */
.data-table-wrap { background:#fff; border-radius:14px; border:1px solid #F3F4F6; overflow:hidden; }
.data-table { width:100%; border-collapse:collapse; }
.data-table thead th { padding:11px 16px; text-align:left; font-size:11px; font-weight:600; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.6px; background:#FAFAFA; border-bottom:1px solid #F3F4F6; white-space:nowrap; }
.data-table tbody td { padding:12px 16px; border-bottom:1px solid #F9FAFB; font-size:13px; color:#1F2933; vertical-align:middle; }
.data-table tbody tr:last-child td { border-bottom:none; }
.data-table tbody tr:hover td { background:#FAFBFF; }

/* Avatar */
.user-avatar { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex-shrink:0; object-fit:cover; }
.user-info { display:flex; align-items:center; gap:10px; }
.user-info__name  { font-size:13px; font-weight:600; color:#1F2933; }
.user-info__phone { font-size:11px; color:#9CA3AF; margin-top:1px; }

/* Badges */
.badge { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap; }
.badge-driver    { background:rgba(26,95,180,0.10);  color:#1A5FB4; }
.badge-passenger { background:rgba(79,70,229,0.10);  color:#4F46E5; }
.badge-approved  { background:rgba(22,163,74,0.10);  color:#16A34A; }
.badge-pending   { background:rgba(217,119,6,0.10);  color:#D97706; }
.badge-rejected  { background:rgba(220,38,38,0.10);  color:#DC2626; }
.badge-none      { background:#F3F4F6; color:#9CA3AF; }
.badge-active    { background:rgba(22,163,74,0.10);  color:#16A34A; }
.badge-blocked   { background:rgba(220,38,38,0.10);  color:#DC2626; }

/* Action buttons */
.action-btns { display:flex; gap:5px; }
.action-btn { width:30px; height:30px; border-radius:7px; border:1px solid #E5E7EB; background:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; color:#6B7684; }
.action-btn:hover            { border-color:#1A5FB4; color:#1A5FB4; background:#EFF6FF; }
.action-btn--danger:hover    { border-color:#DC2626; color:#DC2626; background:#FEF2F2; }
.action-btn--warn:hover      { border-color:#D97706; color:#D97706; background:#FFFBEB; }
.action-btn--success:hover   { border-color:#16A34A; color:#16A34A; background:#F0FDF4; }

/* ── Slide-over panel ─────────────────────────────── */
.panel-overlay {
    position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:100;
    display:flex; justify-content:flex-end;
    animation:fadeIn .2s ease;
}
@keyframes fadeIn { from{opacity:0} to{opacity:1} }

.panel-drawer {
    width:420px; max-width:100vw; background:#fff; height:100vh;
    overflow-y:auto; display:flex; flex-direction:column;
    box-shadow:-4px 0 24px rgba(0,0,0,0.12);
    animation:slideIn .25s ease;
}
@keyframes slideIn { from{transform:translateX(100%)} to{transform:translateX(0)} }

.panel-header {
    padding:20px; border-bottom:1px solid #F3F4F6;
    display:flex; justify-content:space-between; align-items:center;
    flex-shrink:0; position:sticky; top:0; background:#fff; z-index:2;
}
.panel-header__title { font-size:15px; font-weight:700; color:#1F2933; }
.panel-close { width:32px; height:32px; border-radius:8px; border:1px solid #E5E7EB; background:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; color:#6B7684; transition:all .15s; }
.panel-close:hover { background:#FEF2F2; border-color:#FECACA; color:#DC2626; }
.panel-body { padding:20px; flex:1; }

/* Panel avatar */
.panel-avatar-wrap { display:flex; flex-direction:column; align-items:center; padding:20px 0 24px; gap:12px; }
.panel-avatar { width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid #E5E7EB; }
.panel-avatar-initials { width:80px; height:80px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:28px; font-weight:700; border:3px solid #DBEAFE; }
.panel-name  { font-size:17px; font-weight:700; color:#1F2933; text-align:center; }
.panel-phone { font-size:13px; color:#9CA3AF; }

/* Panel sections */
.panel-section { margin-bottom:20px; }
.panel-section__title { font-size:10px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:10px; }
.panel-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #F9FAFB; font-size:13px; }
.panel-row:last-child { border-bottom:none; }
.panel-row__label { color:#6B7684; font-weight:500; }
.panel-row__value { color:#1F2933; font-weight:600; text-align:right; }

/* Panel photos */
.panel-photos { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.panel-photo { border-radius:8px; overflow:hidden; aspect-ratio:4/3; background:#F3F4F6; display:flex; align-items:center; justify-content:center; }
.panel-photo img { width:100%; height:100%; object-fit:cover; }
.panel-photo__label { font-size:10px; color:#9CA3AF; margin-top:4px; }

/* Panel actions */
.panel-actions { padding:16px 20px; border-top:1px solid #F3F4F6; display:flex; gap:8px; flex-shrink:0; position:sticky; bottom:0; background:#fff; }
.panel-action-btn { flex:1; padding:10px; border-radius:9px; border:1px solid; font-size:13px; font-weight:600; font-family:inherit; cursor:pointer; transition:all .15s; display:flex; align-items:center; justify-content:center; gap:6px; }
.panel-action-btn--approve { border-color:#BBF7D0; color:#16A34A; background:#F0FDF4; }
.panel-action-btn--approve:hover { background:#16A34A; color:#fff; border-color:#16A34A; }
.panel-action-btn--reject  { border-color:#FECACA; color:#DC2626; background:#FEF2F2; }
.panel-action-btn--reject:hover  { background:#DC2626; color:#fff; border-color:#DC2626; }
.panel-action-btn--block  { border-color:#FDE68A; color:#D97706; background:#FFFBEB; }
.panel-action-btn--block:hover  { background:#D97706; color:#fff; border-color:#D97706; }
.panel-action-btn--unblock { border-color:#BBF7D0; color:#16A34A; background:#F0FDF4; }
.panel-action-btn--unblock:hover { background:#16A34A; color:#fff; border-color:#16A34A; }
.panel-action-btn--delete { border-color:#FECACA; color:#DC2626; background:#FEF2F2; }
.panel-action-btn--delete:hover { background:#DC2626; color:#fff; border-color:#DC2626; }

/* Empty */
.empty-state { padding:60px 20px; text-align:center; }
.empty-state__icon  { width:56px; height:56px; border-radius:14px; background:#F3F4F6; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
.empty-state__title { font-size:15px; font-weight:600; color:#1F2933; margin-bottom:4px; }
.empty-state__sub   { font-size:13px; color:#9CA3AF; }
.table-footer { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-top:1px solid #F3F4F6; background:#FAFAFA; flex-wrap:wrap; gap:8px; }
.table-footer__info { font-size:12px; color:#9CA3AF; }
</style>

{{-- Header --}}
<div class="page-header">
    <div>
        <div class="page-header__title">Utilisateurs</div>
        <div class="page-header__sub">Gestion des conducteurs et passagers inscrits</div>
    </div>
</div>

{{-- Stats --}}
<div class="stat-bar">
    <div class="stat-mini" wire:click="$set('tab','all')">
        <div class="stat-mini__icon" style="background:rgba(26,95,180,0.10)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1A5FB4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div><div class="stat-mini__val">{{ number_format($stats['total']) }}</div><div class="stat-mini__lbl">Total inscrits</div></div>
    </div>
    <div class="stat-mini" wire:click="$set('tab','driver')">
        <div class="stat-mini__icon" style="background:rgba(26,95,180,0.10)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1A5FB4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        </div>
        <div><div class="stat-mini__val">{{ number_format($stats['drivers']) }}</div><div class="stat-mini__lbl">Conducteurs</div></div>
    </div>
    <div class="stat-mini" wire:click="$set('tab','passenger')">
        <div class="stat-mini__icon" style="background:rgba(79,70,229,0.10)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div><div class="stat-mini__val">{{ number_format($stats['passengers']) }}</div><div class="stat-mini__lbl">Passagers</div></div>
    </div>
    <div class="stat-mini" wire:click="$set('statFilter','blocked')">
        <div class="stat-mini__icon" style="background:rgba(220,38,38,0.10)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        </div>
        <div><div class="stat-mini__val" style="color:{{ $stats['blocked'] > 0 ? '#DC2626' : '#1F2933' }}">{{ number_format($stats['blocked']) }}</div><div class="stat-mini__lbl">Comptes bloqués</div></div>
    </div>
</div>

{{-- Tabs --}}
<div class="tab-bar">
    <button class="tab-btn {{ $tab === 'all'       ? 'active' : '' }}" wire:click="$set('tab','all')">Tous ({{ $stats['total'] }})</button>
    <button class="tab-btn {{ $tab === 'driver'    ? 'active' : '' }}" wire:click="$set('tab','driver')">Conducteurs</button>
    <button class="tab-btn {{ $tab === 'passenger' ? 'active' : '' }}" wire:click="$set('tab','passenger')">Passagers</button>
</div>

{{-- Filtres --}}
<div class="filter-bar">
    <div class="filter-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher par nom, téléphone, email…">
    </div>
    <select class="filter-select" wire:model.live="kycFilter">
        <option value="">KYC — Tous</option>
        <option value="pending">En attente</option>
        <option value="approved">Approuvé</option>
        <option value="rejected">Rejeté</option>
    </select>
    <select class="filter-select" wire:model.live="statFilter">
        <option value="">Statut — Tous</option>
        <option value="active">Actif</option>
        <option value="blocked">Bloqué</option>
    </select>
    @if($statFilter || $kycFilter || $search)
    <button wire:click="$set('statFilter',''); $set('kycFilter',''); $set('search','')" style="padding:8px 12px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;font-size:12px;color:#6B7684;cursor:pointer;font-family:inherit">
        Réinitialiser
    </button>
    @endif
</div>

{{-- Table --}}
<div class="data-table-wrap">
    @if($users->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>Utilisateur</th>
                <th>Rôle</th>
                <th>KYC</th>
                <th>Statut</th>
                <th>Points</th>
                <th>Inscription</th>
                <th style="text-align:center">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            @php
                $isDriver  = $user->role_id == $driverRoleId;
                $name      = $user->profile ? trim(($user->profile->first_name ?? '') . ' ' . ($user->profile->last_name ?? '')) : '';
                $initials  = $name
                    ? strtoupper(substr($user->profile->first_name ?? '?', 0, 1) . substr($user->profile->last_name ?? '', 0, 1))
                    : strtoupper(substr($user->phone, -2));
                $avatarBg  = $isDriver ? '#DBEAFE' : '#EDE9FE';
                $avatarClr = $isDriver ? '#1A5FB4' : '#7C3AED';
                $kyc       = $user->profile?->kyc_status ?? null;
                $photoUrl  = null;
                if ($user->profile?->selfie_front) {
                    $p = $user->profile->selfie_front;
                    $photoUrl = str_starts_with($p, 'http') ? $p : \Illuminate\Support\Facades\Storage::disk('public')->url($p);
                }
                $kycMap = ['approved'=>['label'=>'Approuvé','class'=>'badge-approved'],'pending'=>['label'=>'En attente','class'=>'badge-pending'],'rejected'=>['label'=>'Rejeté','class'=>'badge-rejected']];
            @endphp
            <tr style="{{ $selectedUserId == $user->id ? 'background:#EFF6FF;' : '' }}">
                <td>
                    <div class="user-info">
                        @if($photoUrl)
                            <img src="{{ $photoUrl }}" alt="{{ $initials }}" class="user-avatar"
                                 style="border:2px solid {{ $avatarBg }}"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        @endif
                        <div class="user-avatar" style="background:{{ $avatarBg }};color:{{ $avatarClr }};{{ $photoUrl ? 'display:none' : '' }}">{{ $initials }}</div>
                        <div>
                            <div class="user-info__name">{{ $name ?: '— Sans profil —' }}</div>
                            <div class="user-info__phone">{{ $user->phone }}</div>
                        </div>
                    </div>
                </td>
                <td><span class="badge {{ $isDriver ? 'badge-driver' : 'badge-passenger' }}">{{ $isDriver ? 'Conducteur' : 'Passager' }}</span></td>
                <td>
                    @if($kyc && isset($kycMap[$kyc]))
                        <span class="badge {{ $kycMap[$kyc]['class'] }}">{{ $kycMap[$kyc]['label'] }}</span>
                    @else
                        <span class="badge badge-none">—</span>
                    @endif
                </td>
                <td><span class="badge {{ $user->is_blocked ? 'badge-blocked' : 'badge-active' }}">{{ $user->is_blocked ? 'Bloqué' : 'Actif' }}</span></td>
                <td><span style="font-size:12px;font-weight:600;color:{{ $user->penalty_points > 0 ? '#D97706' : '#9CA3AF' }}">{{ $user->penalty_points ?? 0 }} pts</span></td>
                <td style="color:#6B7684;font-size:12px;white-space:nowrap">{{ $user->created_at?->format('d/m/Y') }}</td>
                <td>
                    <div class="action-btns" style="justify-content:center">
                        {{-- Voir --}}
                        <button class="action-btn" wire:click="viewUser({{ $user->id }})" title="Voir le profil">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        {{-- Bloquer / Débloquer --}}
                        @if($user->is_blocked)
                        <button class="action-btn action-btn--success" wire:click="toggleBlock({{ $user->id }})" wire:confirm="Débloquer cet utilisateur ?" title="Débloquer">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                        </button>
                        @else
                        <button class="action-btn action-btn--warn" wire:click="toggleBlock({{ $user->id }})" wire:confirm="Bloquer cet utilisateur ?" title="Bloquer">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </button>
                        @endif
                        {{-- Supprimer --}}
                        <button class="action-btn action-btn--danger" wire:click="deleteUser({{ $user->id }})" wire:confirm="Supprimer définitivement cet utilisateur et toutes ses données ?" title="Supprimer">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="table-footer">
        <span class="table-footer__info">{{ $users->firstItem() }}–{{ $users->lastItem() }} sur {{ $users->total() }} utilisateurs</span>
        {{ $users->links() }}
    </div>
    @else
    <div class="empty-state">
        <div class="empty-state__icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="empty-state__title">Aucun utilisateur trouvé</div>
        <div class="empty-state__sub">Modifie les filtres ou la recherche</div>
    </div>
    @endif
</div>

{{-- ── SLIDE-OVER PANEL ─────────────────────────────────── --}}
@if($selectedUser)
@php
    $u = $selectedUser;
    $p = $u->profile;
    $v = $u->vehicle;
    $uName = $p ? trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')) : '';
    $uInitials = $uName
        ? strtoupper(substr($p->first_name ?? '?', 0, 1) . substr($p->last_name ?? '', 0, 1))
        : strtoupper(substr($u->phone, -2));
    $uIsDriver = $u->role_id == $driverRoleId;
    $uAvatarBg = $uIsDriver ? '#DBEAFE' : '#EDE9FE';
    $uAvatarCl = $uIsDriver ? '#1A5FB4' : '#7C3AED';
    $uPhotoUrl = null;
    if ($p?->selfie_front) {
        $pp = $p->selfie_front;
        $uPhotoUrl = str_starts_with($pp, 'http') ? $pp : \Illuminate\Support\Facades\Storage::disk('public')->url($pp);
    }
    $kycLabels = ['approved'=>'Approuvé','pending'=>'En attente','rejected'=>'Rejeté'];
    $kycColors = ['approved'=>'#16A34A','pending'=>'#D97706','rejected'=>'#DC2626'];
@endphp
<div class="panel-overlay" wire:click.self="closeView">
    <div class="panel-drawer">

        {{-- Header --}}
        <div class="panel-header">
            <div class="panel-header__title">Profil utilisateur</div>
            <button class="panel-close" wire:click="closeView" title="Fermer">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Avatar + nom --}}
        <div class="panel-avatar-wrap">
            @if($uPhotoUrl)
                <img src="{{ $uPhotoUrl }}" alt="{{ $uInitials }}" class="panel-avatar"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            @endif
            <div class="panel-avatar-initials" style="background:{{ $uAvatarBg }};color:{{ $uAvatarCl }};{{ $uPhotoUrl ? 'display:none' : '' }}">{{ $uInitials }}</div>
            <div>
                <div class="panel-name">{{ $uName ?: '— Sans profil —' }}</div>
                <div class="panel-phone">{{ $u->phone }}</div>
            </div>
            <span class="badge {{ $uIsDriver ? 'badge-driver' : 'badge-passenger' }}">
                {{ $uIsDriver ? 'Conducteur' : 'Passager' }}
            </span>
        </div>

        <div class="panel-body">

            {{-- Infos compte --}}
            <div class="panel-section">
                <div class="panel-section__title">Compte</div>
                <div class="panel-row">
                    <span class="panel-row__label">Statut</span>
                    <span class="panel-row__value"><span class="badge {{ $u->is_blocked ? 'badge-blocked' : 'badge-active' }}">{{ $u->is_blocked ? 'Bloqué' : 'Actif' }}</span></span>
                </div>
                <div class="panel-row">
                    <span class="panel-row__label">KYC</span>
                    <span class="panel-row__value" style="color:{{ $kycColors[$p?->kyc_status] ?? '#9CA3AF' }}">{{ $kycLabels[$p?->kyc_status] ?? '—' }}</span>
                </div>
                @if($p?->kyc_matching_score)
                <div class="panel-row">
                    <span class="panel-row__label">Score KYC</span>
                    <span class="panel-row__value">{{ number_format($p->kyc_matching_score, 1) }}%</span>
                </div>
                @endif
                <div class="panel-row">
                    <span class="panel-row__label">Points pénalité</span>
                    <span class="panel-row__value" style="color:{{ $u->penalty_points > 0 ? '#D97706' : '#16A34A' }}">{{ $u->penalty_points ?? 0 }} pts</span>
                </div>
                <div class="panel-row">
                    <span class="panel-row__label">Inscrit le</span>
                    <span class="panel-row__value">{{ $u->created_at?->format('d/m/Y à H:i') }}</span>
                </div>
            </div>

            {{-- Infos profil --}}
            @if($p)
            <div class="panel-section">
                <div class="panel-section__title">Informations personnelles</div>
                @if($p->email)
                <div class="panel-row"><span class="panel-row__label">Email</span><span class="panel-row__value">{{ $p->email }}</span></div>
                @endif
                @if($p->gender)
                <div class="panel-row"><span class="panel-row__label">Genre</span><span class="panel-row__value">{{ ucfirst($p->gender) }}</span></div>
                @endif
                @if($p->city)
                <div class="panel-row"><span class="panel-row__label">Ville</span><span class="panel-row__value">{{ $p->city }}</span></div>
                @endif
                @if($p->driving_license_number)
                <div class="panel-row"><span class="panel-row__label">N° Permis</span><span class="panel-row__value" style="font-family:monospace">{{ $p->driving_license_number }}</span></div>
                @endif
            </div>
            @endif

            {{-- Véhicule (si conducteur) --}}
            @if($uIsDriver && $v)
            <div class="panel-section">
                <div class="panel-section__title">Véhicule</div>
                <div class="panel-row"><span class="panel-row__label">Modèle</span><span class="panel-row__value">{{ $v->brand }} {{ $v->model }}</span></div>
                <div class="panel-row"><span class="panel-row__label">Couleur</span><span class="panel-row__value">{{ $v->color }}</span></div>
                <div class="panel-row"><span class="panel-row__label">Plaque</span><span class="panel-row__value" style="font-family:monospace">{{ $v->license_plate }}</span></div>
                <div class="panel-row"><span class="panel-row__label">Places</span><span class="panel-row__value">{{ $v->available_seats }}</span></div>
                <div class="panel-row">
                    <span class="panel-row__label">Vérification</span>
                    <span class="badge {{ $v->verification_status === 'approved' ? 'badge-approved' : ($v->verification_status === 'rejected' ? 'badge-rejected' : 'badge-pending') }}">
                        {{ $v->verification_status ?? 'En attente' }}
                    </span>
                </div>
            </div>
            @endif

            {{-- Photos selfies --}}
            @if($p && ($p->selfie_front || $p->selfie_left || $p->selfie_right))
            <div class="panel-section">
                <div class="panel-section__title">Photos selfies</div>
                <div class="panel-photos">
                    @foreach(['selfie_front'=>'Face','selfie_left'=>'Gauche','selfie_right'=>'Droite'] as $field => $label)
                        @if($p->$field)
                        @php $url = str_starts_with($p->$field, 'http') ? $p->$field : \Illuminate\Support\Facades\Storage::disk('public')->url($p->$field); @endphp
                        <div>
                            <div class="panel-photo">
                                <img src="{{ $url }}" alt="{{ $label }}" loading="lazy"
                                     onerror="this.parentElement.innerHTML='<span style=\'font-size:11px;color:#9CA3AF\'>Non disponible</span>'">
                            </div>
                            <div class="panel-photo__label">{{ $label }}</div>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Pièce d'identité --}}
            @if($p && ($p->id_card_front || $p->id_card_back))
            <div class="panel-section">
                <div class="panel-section__title">Pièce d'identité</div>
                <div class="panel-photos">
                    @foreach(['id_card_front'=>'Recto','id_card_back'=>'Verso'] as $field => $label)
                        @if($p->$field)
                        @php $url = str_starts_with($p->$field, 'http') ? $p->$field : \Illuminate\Support\Facades\Storage::disk('public')->url($p->$field); @endphp
                        <div>
                            <div class="panel-photo">
                                <img src="{{ $url }}" alt="{{ $label }}" loading="lazy"
                                     onerror="this.parentElement.innerHTML='<span style=\'font-size:11px;color:#9CA3AF\'>Non disponible</span>'">
                            </div>
                            <div class="panel-photo__label">{{ $label }}</div>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif

        </div>

        {{-- Actions --}}
        <div class="panel-actions">
            {{-- KYC --}}
            @if($p?->kyc_status === 'pending' || !$p?->kyc_status)
            <button class="panel-action-btn panel-action-btn--approve" wire:click="approveKyc({{ $u->id }})" wire:confirm="Approuver le KYC de {{ $uName ?: $u->phone }} ?">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Approuver KYC
            </button>
            <button class="panel-action-btn panel-action-btn--reject" wire:click="rejectKyc({{ $u->id }})" wire:confirm="Rejeter le KYC de {{ $uName ?: $u->phone }} ?">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Rejeter KYC
            </button>
            @elseif($p?->kyc_status === 'approved')
            <button class="panel-action-btn panel-action-btn--reject" wire:click="rejectKyc({{ $u->id }})" wire:confirm="Révoquer l'approbation KYC ?">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Révoquer KYC
            </button>
            @elseif($p?->kyc_status === 'rejected')
            <button class="panel-action-btn panel-action-btn--approve" wire:click="approveKyc({{ $u->id }})" wire:confirm="Ré-approuver le KYC ?">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Ré-approuver KYC
            </button>
            @endif
            {{-- Bloquer / Débloquer --}}
            @if($u->is_blocked)
            <button class="panel-action-btn panel-action-btn--unblock" wire:click="toggleBlock({{ $u->id }})" wire:confirm="Débloquer cet utilisateur ?">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                Débloquer
            </button>
            @else
            <button class="panel-action-btn panel-action-btn--block" wire:click="toggleBlock({{ $u->id }})" wire:confirm="Bloquer cet utilisateur ?">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Bloquer
            </button>
            @endif
            <button class="panel-action-btn panel-action-btn--delete" wire:click="deleteUser({{ $u->id }})" wire:confirm="Supprimer définitivement {{ $uName ?: $u->phone }} ?">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                Supprimer
            </button>
        </div>

    </div>
</div>
@endif

</div>
