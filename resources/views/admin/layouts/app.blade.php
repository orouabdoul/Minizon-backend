<!DOCTYPE html>
<html lang="fr" x-data="{ sidebarOpen: false }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MINIZON Admin — {{ $title ?? 'Dashboard' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @livewireStyles
    <style>
        :root {
            --primary:        #1A5FB4;
            --primary-dark:   #0F4A9E;
            --primary-light:  rgba(26,95,180,0.10);
            --accent:         #FF7A45;
            --success:        #17A398;
            --warning:        #F5A623;
            --error:          #E5484D;
            --bg:             #F2F4F7;
            --surface:        #FFFFFF;
            --text:           #1F2933;
            --text-secondary: #6B7684;
            --text-muted:     #9CA3AF;
            --border:         #D1D5DB;
            --border-light:   #E5E7EB;
            --border-faint:   #F3F4F6;
            --sidebar-width:  260px;
            --header-height:  64px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); }

        /* ── Layout ─────────────────────────────────────────── */
        .dash-layout { display: flex; min-height: 100vh; }

        /* ── Backdrop mobile ────────────────────────────────── */
        .dash-backdrop {
            position: fixed; inset: 0; background: rgba(0,0,0,0.4);
            z-index: 40; display: none;
        }
        @media (max-width: 1023px) {
            .dash-backdrop { display: block; }
        }

        /* ── Sidebar ────────────────────────────────────────── */
        .dash-sidebar {
            width: var(--sidebar-width); min-height: 100vh;
            background: linear-gradient(175deg, #1A5FB4 0%, #1352A3 55%, #0F4A9E 100%);
            display: flex; flex-direction: column;
            position: fixed; left: 0; top: 0; bottom: 0;
            z-index: 50; transition: transform 0.25s ease;
            box-shadow: 2px 0 16px rgba(15,74,158,0.25);
        }
        @media (max-width: 1023px) {
            .dash-sidebar { transform: translateX(-100%); }
            .dash-sidebar.open { transform: translateX(0); }
        }

        /* ── Logo ───────────────────────────────────────────── */
        .dash-sidebar__logo {
            padding: 18px 14px 16px;
            display: flex; align-items: center; gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.10);
            flex-shrink: 0;
        }
        .dash-sidebar__logo-box {
            width: 38px; height: 38px;
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .dash-sidebar__logo-title { color: #fff; font-size: 15px; font-weight: 700; line-height: 1.2; letter-spacing: 0.3px; }
        .dash-sidebar__logo-sub   { color: rgba(255,255,255,0.5); font-size: 10px; margin-top:1px; display:flex; align-items:center; gap:5px; }
        .dash-logo-badge {
            background: rgba(255,122,69,0.25); border: 1px solid rgba(255,122,69,0.4);
            color: #FFB59A; font-size: 8px; font-weight: 700; letter-spacing: 0.5px;
            padding: 1px 5px; border-radius: 4px; text-transform: uppercase;
        }
        .dash-sidebar__close {
            margin-left: auto; background: none; border: none; padding: 4px;
            cursor: pointer; display: none; color: white; border-radius: 6px;
        }
        @media (max-width: 1023px) { .dash-sidebar__close { display: flex; } }

        /* ── Nav ────────────────────────────────────────────── */
        .dash-sidebar__nav {
            flex: 1; overflow-y: auto; padding: 10px 8px;
            scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.15) transparent;
        }
        .dash-nav-group { margin-bottom: 2px; }
        .dash-nav-group__label {
            color: rgba(255,255,255,0.38); font-size: 9.5px; font-weight: 700;
            letter-spacing: 1px; text-transform: uppercase;
            padding: 10px 10px 3px;
        }
        .dash-nav-item {
            display: flex; align-items: center; gap: 9px;
            padding: 8.5px 10px; border-radius: 8px;
            color: rgba(255,255,255,0.68); font-size: 13px; font-weight: 500;
            text-decoration: none;
            transition: background 0.15s, color 0.15s, transform 0.12s;
            margin-bottom: 1px; position: relative;
        }
        .dash-nav-item:hover {
            background: rgba(255,255,255,0.10);
            color: #fff;
            transform: translateX(2px);
        }
        .dash-nav-item.active {
            background: rgba(255,255,255,0.15);
            color: #fff;
            font-weight: 600;
            box-shadow: inset 3px 0 0 #FF7A45;
            border-radius: 0 8px 8px 0;
        }
        .dash-nav-item.active svg { opacity: 1; }

        /* ── Footer ─────────────────────────────────────────── */
        .dash-sidebar__footer {
            padding: 10px 8px 14px;
            border-top: 1px solid rgba(255,255,255,0.10);
            flex-shrink: 0;
        }
        .dash-admin-card {
            display: flex; align-items: center; gap: 9px;
            padding: 9px 10px; border-radius: 8px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.10);
            margin-bottom: 6px;
        }
        .dash-admin-card__avatar {
            width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
            background: rgba(255,255,255,0.20);
            border: 2px solid rgba(255,255,255,0.30);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 12px;
        }
        .dash-admin-card__name   { color: #fff; font-size: 12.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dash-admin-card__status { display: flex; align-items: center; gap: 4px; color: rgba(255,255,255,0.5); font-size: 10px; margin-top: 1px; }
        .dash-status-dot         { width: 6px; height: 6px; border-radius: 50%; background: #22C55E; display: inline-block; flex-shrink: 0; box-shadow: 0 0 4px #22C55E; }
        .dash-logout-btn {
            width: 100%; display: flex; align-items: center; gap: 9px;
            padding: 8px 10px; border-radius: 8px;
            background: none; border: none; cursor: pointer;
            color: rgba(255,255,255,0.60); font-size: 13px; font-weight: 500;
            font-family: inherit; transition: background 0.15s, color 0.15s;
        }
        .dash-logout-btn:hover { background: rgba(239,68,68,0.18); color: #FCA5A5; }

        /* ── Main area ──────────────────────────────────────── */
        .dash-main {
            flex: 1; margin-left: var(--sidebar-width);
            display: flex; flex-direction: column; min-height: 100vh;
        }
        @media (max-width: 1023px) { .dash-main { margin-left: 0; } }

        /* ── Header ─────────────────────────────────────────── */
        .dash-header {
            height: var(--header-height); background: var(--surface);
            border-bottom: 1px solid var(--border-light);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; gap: 16px; position: sticky; top: 0; z-index: 30;
        }
        .dash-header__left  { display: flex; align-items: center; gap: 16px; flex: 1; }
        .dash-header__right { display: flex; align-items: center; gap: 12px; }

        .dash-hamburger {
            background: none; border: none; padding: 6px; cursor: pointer;
            border-radius: 6px; display: none; color: var(--text);
        }
        @media (max-width: 1023px) { .dash-hamburger { display: flex; } }

        .dash-header__title { font-size: 18px; font-weight: 700; color: var(--text); white-space: nowrap; }

        .dash-search {
            display: flex; align-items: center; gap: 8px;
            background: var(--bg); border: 1px solid var(--border-light);
            border-radius: 8px; padding: 8px 12px; flex: 1; max-width: 320px;
        }
        .dash-search input {
            border: none; background: transparent; outline: none;
            font-family: inherit; font-size: 14px; color: var(--text);
            width: 100%;
        }
        .dash-search input::placeholder { color: var(--text-muted); }

        .dash-notif-btn {
            position: relative; background: none; border: none; padding: 8px;
            cursor: pointer; border-radius: 8px; display: flex; align-items: center;
            transition: background 0.15s;
        }
        .dash-notif-btn:hover { background: var(--bg); }
        .dash-notif-badge {
            position: absolute; top: 4px; right: 4px;
            background: var(--error); color: white;
            font-size: 9px; font-weight: 700;
            width: 16px; height: 16px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }

        .dash-admin-info { display: flex; align-items: center; gap: 8px; }
        .dash-admin-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--primary-light); border: 2px solid var(--primary);
            display: flex; align-items: center; justify-content: center;
            color: var(--primary); font-weight: 700; font-size: 14px;
        }
        .dash-admin-name { font-size: 14px; font-weight: 600; color: var(--text); }
        .dash-admin-role { font-size: 11px; color: var(--text-secondary); }

        /* ── Page content ───────────────────────────────────── */
        .dash-content { flex: 1; padding: 24px; }

        /* ── KPI Grid ───────────────────────────────────────── */
        .dash-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px; margin-bottom: 24px;
        }
        .kpi-card {
            background: var(--surface); border-radius: 12px;
            border: 1px solid var(--border-faint); padding: 18px;
            display: flex; flex-direction: column; gap: 12px;
        }
        .kpi-card__header { display: flex; justify-content: space-between; align-items: flex-start; }
        .kpi-card__icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .kpi-card__badge {
            font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 20px;
        }
        .kpi-card__badge--success { background: rgba(23,163,152,0.12); color: #17A398; }
        .kpi-card__badge--error   { background: rgba(229,72,77,0.12);  color: #E5484D; }
        .kpi-card__badge--warning { background: rgba(245,166,35,0.12); color: #D97706; }
        .kpi-card__value { font-size: 26px; font-weight: 700; color: var(--text); line-height: 1; }
        .kpi-card__label { font-size: 13px; color: var(--text-secondary); font-weight: 500; }

        /* ── Charts placeholder ─────────────────────────────── */
        .dash-charts-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 16px; margin-bottom: 24px;
        }
        @media (max-width: 900px) { .dash-charts-grid { grid-template-columns: 1fr; } }
        .chart-card {
            background: var(--surface); border-radius: 12px;
            border: 1px solid var(--border-faint); padding: 20px;
        }
        .chart-card__title { font-size: 15px; font-weight: 600; color: var(--text); margin-bottom: 4px; }
        .chart-card__sub   { font-size: 12px; color: var(--text-muted); margin-bottom: 16px; }
        .chart-placeholder {
            height: 160px; background: var(--bg); border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); font-size: 13px; border: 1px dashed var(--border);
        }

        /* ── Activity table ─────────────────────────────────── */
        .activity-card { background: var(--surface); border-radius: 12px; border: 1px solid var(--border-faint); overflow: hidden; }
        .activity-card__header { padding: 16px 20px; border-bottom: 1px solid var(--border-faint); display: flex; justify-content: space-between; align-items: center; }
        .activity-card__title  { font-size: 15px; font-weight: 600; color: var(--text); }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 10px 16px; text-align: left; font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-faint); background: var(--bg); }
        td { padding: 12px 16px; font-size: 13px; color: var(--text); border-bottom: 1px solid var(--border-faint); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #FAFBFF; }

        .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-success { background: rgba(23,163,152,0.12); color: #17A398; }
        .badge-warning { background: rgba(245,166,35,0.12); color: #D97706; }
        .badge-error   { background: rgba(229,72,77,0.12); color: #E5484D; }
        .badge-blue    { background: rgba(26,95,180,0.12); color: #1A5FB4; }
    </style>
</head>
<body>
<div class="dash-layout">

    {{-- Backdrop mobile --}}
    <div class="dash-backdrop" x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"></div>

    {{-- Sidebar --}}
    <aside class="dash-sidebar" :class="{ 'open': sidebarOpen }">
        {{-- Logo --}}
        <div class="dash-sidebar__logo">
            <div class="dash-sidebar__logo-box">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
            </div>
            <div style="flex:1;min-width:0">
                <div class="dash-sidebar__logo-title">MINIZON</div>
                <div class="dash-sidebar__logo-sub">
                    Admin Platform
                    <span class="dash-logo-badge">ADMIN</span>
                </div>
            </div>
            <button class="dash-sidebar__close" @click="sidebarOpen = false" aria-label="Fermer">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="dash-sidebar__nav">
            @php
            $navGroups = [
                ["Vue d'ensemble", [['dashboard','Dashboard','/admin/dashboard']]],
                ['Utilisateurs',   [['users','Utilisateurs','/admin/users'],['drivers','Conducteurs','/admin/drivers'],['passengers','Passagers','/admin/passengers'],['vehicles','Véhicules','/admin/vehicles']]],
                ['Opérations',     [['trips','Trajets','/admin/trips'],['reservations','Réservations','/admin/reservations'],['tracking','Suivi Temps Réel','/admin/tracking'],['messaging','Communication','/admin/messaging']]],
                ['Finance',        [['payments','Paiements','/admin/payments'],['payouts','Virements','/admin/payouts'],['refunds','Remboursements','/admin/refunds']]],
                ['Relation client',[['disputes','Litiges','/admin/disputes'],['support','Support','/admin/support'],['reviews','Évaluations','/admin/reviews']]],
                ['Administration', [['notifications','Notifications','/admin/notifications'],['reports','Rapports','/admin/reports'],['audit',"Journal d'Audit",'/admin/audit'],['settings','Paramètres','/admin/settings']]],
            ];
            $icons = [
                'dashboard'     => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
                'users'         => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                'drivers'       => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
                'passengers'    => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
                'vehicles'      => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
                'trips'         => '<circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>',
                'reservations'  => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
                'tracking'      => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
                'messaging'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
                'payments'      => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
                'payouts'       => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
                'refunds'       => '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/>',
                'disputes'      => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
                'support'       => '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3z"/><path d="M3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/>',
                'reviews'       => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
                'notifications' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
                'reports'       => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
                'audit'         => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
                'settings'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            ];
            $currentPath = request()->path();
            @endphp

            @foreach($navGroups as [$groupLabel, $items])
                <div class="dash-nav-group">
                    <div class="dash-nav-group__label">{{ $groupLabel }}</div>
                    @foreach($items as [$id, $label, $path])
                        <a href="{{ $path }}"
                           class="dash-nav-item {{ ltrim($path, '/') === $currentPath ? 'active' : '' }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                {!! $icons[$id] ?? '' !!}
                            </svg>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        {{-- Footer : profil + logout --}}
        <div class="dash-sidebar__footer">
            <div class="dash-admin-card">
                <div class="dash-admin-card__avatar">
                    {{ strtoupper(substr(Auth::guard('admin')->user()->name ?? 'A', 0, 1)) }}
                </div>
                <div style="flex:1;min-width:0">
                    <div class="dash-admin-card__name">{{ Auth::guard('admin')->user()->name ?? 'Admin' }}</div>
                    <div class="dash-admin-card__status">
                        <span class="dash-status-dot"></span>
                        En ligne
                    </div>
                </div>
            </div>
            <form method="POST" action="{{ route('panel.logout') }}">
                @csrf
                <button type="submit" class="dash-logout-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Se déconnecter
                </button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <div class="dash-main">

        {{-- Header --}}
        <header class="dash-header">
            <div class="dash-header__left">
                <button class="dash-hamburger" @click="sidebarOpen = true" aria-label="Menu">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <h1 class="dash-header__title">{{ $title ?? 'Dashboard' }}</h1>
                <div class="dash-search">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" placeholder="Rechercher…">
                </div>
            </div>
            <div class="dash-header__right">
                <a href="/admin/notifications" class="dash-notif-btn" title="Notifications">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4B5563" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </a>
                <div class="dash-admin-info">
                    <div class="dash-admin-avatar">
                        {{ strtoupper(substr(Auth::guard('admin')->user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div>
                        <div class="dash-admin-name">{{ Auth::guard('admin')->user()->name ?? 'Admin' }}</div>
                        <div class="dash-admin-role">Administrateur</div>
                    </div>
                </div>
            </div>
        </header>

        {{-- Content --}}
        <main class="dash-content">
            {{ $slot }}
        </main>

    </div>
</div>

@livewireScripts
</body>
</html>
