<div>
<style>
/* ── Page header ──────────────────────────────────────── */
.page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; }
.page-header__title { font-size:22px; font-weight:700; color:#1F2933; }
.page-header__sub   { font-size:13px; color:#6B7684; margin-top:2px; }

/* ── Stats bar ────────────────────────────────────────── */
.stat-bar { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
@media(max-width:900px){ .stat-bar { grid-template-columns:repeat(2,1fr); } }
.stat-mini {
    background:#fff; border-radius:12px; border:1px solid #F3F4F6;
    padding:14px 16px; display:flex; align-items:center; gap:12px;
}
.stat-mini__icon { width:38px; height:38px; border-radius:9px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.stat-mini__val  { font-size:20px; font-weight:700; color:#1F2933; line-height:1; }
.stat-mini__lbl  { font-size:11px; color:#9CA3AF; margin-top:2px; }

/* ── Filter bar ───────────────────────────────────────── */
.filter-bar { background:#fff; border:1px solid #F3F4F6; border-radius:12px; padding:14px 16px; margin-bottom:16px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
.filter-search {
    display:flex; align-items:center; gap:8px;
    background:#F9FAFB; border:1px solid #E5E7EB; border-radius:8px;
    padding:8px 12px; flex:1; min-width:200px;
}
.filter-search input { border:none; background:transparent; outline:none; font-family:inherit; font-size:13px; color:#1F2933; width:100%; }
.filter-search input::placeholder { color:#9CA3AF; }
.filter-select {
    border:1px solid #E5E7EB; border-radius:8px; padding:8px 12px;
    font-size:13px; color:#374151; background:#F9FAFB; cursor:pointer;
    font-family:inherit; outline:none;
}
.filter-select:focus { border-color:#1A5FB4; }

/* ── Tabs ─────────────────────────────────────────────── */
.tab-bar { display:flex; gap:4px; background:#F3F4F6; border-radius:10px; padding:4px; margin-bottom:16px; width:fit-content; }
.tab-btn {
    padding:7px 18px; border-radius:7px; font-size:13px; font-weight:500;
    border:none; background:transparent; cursor:pointer; color:#6B7684;
    transition:all .15s; font-family:inherit;
}
.tab-btn.active { background:#fff; color:#1A5FB4; font-weight:600; box-shadow:0 1px 4px rgba(0,0,0,0.08); }
.tab-btn:hover:not(.active) { color:#1F2933; }

/* ── Table ────────────────────────────────────────────── */
.data-table-wrap { background:#fff; border-radius:14px; border:1px solid #F3F4F6; overflow:hidden; }
.data-table { width:100%; border-collapse:collapse; }
.data-table thead th {
    padding:11px 16px; text-align:left; font-size:11px; font-weight:600;
    color:#9CA3AF; text-transform:uppercase; letter-spacing:0.6px;
    background:#FAFAFA; border-bottom:1px solid #F3F4F6; white-space:nowrap;
}
.data-table tbody td { padding:13px 16px; border-bottom:1px solid #F9FAFB; font-size:13px; color:#1F2933; vertical-align:middle; }
.data-table tbody tr:last-child td { border-bottom:none; }
.data-table tbody tr:hover td { background:#FAFBFF; }

/* ── Avatar ───────────────────────────────────────────── */
.user-avatar {
    width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center;
    font-weight:700; font-size:13px; flex-shrink:0;
}
.user-info { display:flex; align-items:center; gap:10px; }
.user-info__name  { font-size:13px; font-weight:600; color:#1F2933; }
.user-info__phone { font-size:11px; color:#9CA3AF; margin-top:1px; }

/* ── Badges ───────────────────────────────────────────── */
.badge { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap; }
.badge-driver    { background:rgba(26,95,180,0.10);  color:#1A5FB4; }
.badge-passenger { background:rgba(79,70,229,0.10);  color:#4F46E5; }
.badge-approved  { background:rgba(22,163,74,0.10);  color:#16A34A; }
.badge-pending   { background:rgba(217,119,6,0.10);  color:#D97706; }
.badge-rejected  { background:rgba(220,38,38,0.10);  color:#DC2626; }
.badge-none      { background:#F3F4F6; color:#9CA3AF; }
.badge-active    { background:rgba(22,163,74,0.10);  color:#16A34A; }
.badge-blocked   { background:rgba(220,38,38,0.10);  color:#DC2626; }

/* ── Action buttons ───────────────────────────────────── */
.action-btns { display:flex; gap:6px; }
.action-btn {
    width:30px; height:30px; border-radius:7px; border:1px solid #E5E7EB;
    background:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center;
    transition:all .15s; color:#6B7684;
}
.action-btn:hover { border-color:#1A5FB4; color:#1A5FB4; background:#EFF6FF; }
.action-btn--danger:hover { border-color:#DC2626; color:#DC2626; background:#FEF2F2; }
.action-btn--success:hover { border-color:#16A34A; color:#16A34A; background:#F0FDF4; }

/* ── Empty state ──────────────────────────────────────── */
.empty-state { padding:60px 20px; text-align:center; }
.empty-state__icon { width:56px; height:56px; border-radius:14px; background:#F3F4F6; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
.empty-state__title { font-size:15px; font-weight:600; color:#1F2933; margin-bottom:4px; }
.empty-state__sub   { font-size:13px; color:#9CA3AF; }

/* ── Pagination ───────────────────────────────────────── */
.table-footer { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-top:1px solid #F3F4F6; background:#FAFAFA; }
.table-footer__info { font-size:12px; color:#9CA3AF; }
</style>

{{-- Header --}}
<div class="page-header">
    <div>
        <div class="page-header__title">Utilisateurs</div>
        <div class="page-header__sub">Gestion des conducteurs et passagers inscrits</div>
    </div>
</div>

{{-- Stats bar --}}
<div class="stat-bar">
    <div class="stat-mini">
        <div class="stat-mini__icon" style="background:rgba(26,95,180,0.10)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1A5FB4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <div>
            <div class="stat-mini__val">{{ number_format($stats['total']) }}</div>
            <div class="stat-mini__lbl">Total inscrits</div>
        </div>
    </div>
    <div class="stat-mini">
        <div class="stat-mini__icon" style="background:rgba(26,95,180,0.10)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1A5FB4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
        </div>
        <div>
            <div class="stat-mini__val">{{ number_format($stats['drivers']) }}</div>
            <div class="stat-mini__lbl">Conducteurs</div>
        </div>
    </div>
    <div class="stat-mini">
        <div class="stat-mini__icon" style="background:rgba(79,70,229,0.10)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
        </div>
        <div>
            <div class="stat-mini__val">{{ number_format($stats['passengers']) }}</div>
            <div class="stat-mini__lbl">Passagers</div>
        </div>
    </div>
    <div class="stat-mini">
        <div class="stat-mini__icon" style="background:rgba(220,38,38,0.10)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
            </svg>
        </div>
        <div>
            <div class="stat-mini__val" style="color:{{ $stats['blocked'] > 0 ? '#DC2626' : '#1F2933' }}">{{ number_format($stats['blocked']) }}</div>
            <div class="stat-mini__lbl">Comptes bloqués</div>
        </div>
    </div>
</div>

{{-- Tabs --}}
<div class="tab-bar">
    <button class="tab-btn {{ $tab === 'all' ? 'active' : '' }}" wire:click="$set('tab','all')">Tous ({{ $stats['total'] }})</button>
    <button class="tab-btn {{ $tab === 'driver' ? 'active' : '' }}" wire:click="$set('tab','driver')">Conducteurs</button>
    <button class="tab-btn {{ $tab === 'passenger' ? 'active' : '' }}" wire:click="$set('tab','passenger')">Passagers</button>
</div>

{{-- Filtres --}}
<div class="filter-bar">
    <div class="filter-search" style="flex:1;min-width:220px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
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
                <th>Points pénalité</th>
                <th>Inscription</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            @php
                $isDriver  = $user->role_id == $driverRoleId;
                $name      = $user->profile ? trim(($user->profile->first_name ?? '') . ' ' . ($user->profile->last_name ?? '')) : '';
                $initials  = $name ? strtoupper(substr($user->profile->first_name ?? '?', 0, 1) . substr($user->profile->last_name ?? '', 0, 1)) : strtoupper(substr($user->phone, -2));
                $avatarBg  = $isDriver ? '#DBEAFE' : '#EDE9FE';
                $avatarClr = $isDriver ? '#1A5FB4' : '#7C3AED';
                $kyc       = $user->profile?->kyc_status ?? null;
                $kycMap    = ['approved'=>['label'=>'Approuvé','class'=>'badge-approved'],'pending'=>['label'=>'En attente','class'=>'badge-pending'],'rejected'=>['label'=>'Rejeté','class'=>'badge-rejected']];
            @endphp
            <tr>
                <td>
                    <div class="user-info">
                        <div class="user-avatar" style="background:{{ $avatarBg }};color:{{ $avatarClr }}">{{ $initials }}</div>
                        <div>
                            <div class="user-info__name">{{ $name ?: '— Sans profil —' }}</div>
                            <div class="user-info__phone">{{ $user->phone }}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge {{ $isDriver ? 'badge-driver' : 'badge-passenger' }}">
                        {{ $isDriver ? 'Conducteur' : 'Passager' }}
                    </span>
                </td>
                <td>
                    @if($kyc && isset($kycMap[$kyc]))
                        <span class="badge {{ $kycMap[$kyc]['class'] }}">{{ $kycMap[$kyc]['label'] }}</span>
                    @else
                        <span class="badge badge-none">—</span>
                    @endif
                </td>
                <td>
                    <span class="badge {{ $user->is_blocked ? 'badge-blocked' : 'badge-active' }}">
                        {{ $user->is_blocked ? 'Bloqué' : 'Actif' }}
                    </span>
                </td>
                <td>
                    <span style="font-size:12px;font-weight:600;color:{{ $user->penalty_points > 0 ? '#D97706' : '#9CA3AF' }}">
                        {{ $user->penalty_points ?? 0 }} pts
                    </span>
                </td>
                <td style="color:#6B7684;font-size:12px;white-space:nowrap">
                    {{ $user->created_at?->format('d/m/Y') }}<br>
                    <span style="color:#9CA3AF">{{ $user->created_at?->diffForHumans() }}</span>
                </td>
                <td>
                    <div class="action-btns">
                        {{-- Bloquer / Débloquer --}}
                        @if($user->is_blocked)
                        <button class="action-btn action-btn--success"
                            wire:click="toggleBlock({{ $user->id }})"
                            wire:confirm="Débloquer cet utilisateur ?"
                            title="Débloquer">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>
                            </svg>
                        </button>
                        @else
                        <button class="action-btn action-btn--danger"
                            wire:click="toggleBlock({{ $user->id }})"
                            wire:confirm="Bloquer cet utilisateur ? Il ne pourra plus se connecter."
                            title="Bloquer">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </button>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Footer pagination --}}
    <div class="table-footer">
        <span class="table-footer__info">
            {{ $users->firstItem() }}–{{ $users->lastItem() }} sur {{ $users->total() }} utilisateurs
        </span>
        {{ $users->links() }}
    </div>

    @else
    <div class="empty-state">
        <div class="empty-state__icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <div class="empty-state__title">Aucun utilisateur trouvé</div>
        <div class="empty-state__sub">Modifie les filtres ou la recherche</div>
    </div>
    @endif
</div>

</div>
