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

        /* ── Responsive global ───────────────────────────────── */
        @media (max-width: 768px) {
            .dash-content { padding: 12px; }
            .dash-search { display: none; }
            .dash-header__title { font-size: 15px; }
            .dash-header { padding: 0 12px; gap: 8px; }
            .dash-admin-name, .dash-admin-role { display: none; }
            .dash-admin-avatar { width: 32px; height: 32px; font-size: 12px; }
        }
        @media (max-width: 480px) {
            .dash-content { padding: 8px; }
            .dash-header { padding: 0 8px; }
            .dash-kpi-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 360px) {
            .dash-kpi-grid { grid-template-columns: 1fr; }
        }
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
            <div class="dash-sidebar__logo-box" style="background:rgba(255,255,255,0.95);border:none;">
                <img src="{{ asset('images/logo.png') }}" alt="MINIZON" style="width:28px;height:28px;object-fit:contain;">
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

{{-- ═══════════════════════════════════════════════════════════
     MINIZON Admin — Responsive global overrides
     Placé en FIN de <body> : même spécificité que les styles
     de page, mais position plus tardive → cascade prioritaire.
     Corrige : padding wrappers, grilles stat, filtres, tables,
     panels, tab-bars sur tous les écrans mobiles/tablettes.
     ═══════════════════════════════════════════════════════════ --}}
<style>

/* ── 1. Réduire le padding des wrappers de page sur mobile ── */
/* Toutes les pages ont padding:28px 32px sur leur wrapper     */
/* → on annule le padding horizontal et laisse dash-content   */
/* gérer les marges. Le padding vertical reste réduit.        */
@media (max-width: 1024px) {
    .pay-wrap,.pyo-wrap,.usr-wrap,.drv-wrap,
    .pax-wrap,.res-wrap,.rf-wrap,.rpt-wrap,
    .rv-wrap,.set-wrap,.sup-wrap,.track-wrap,.trips-wrap,
    .veh-wrap,.disp-wrap,.comm-wrap,.notif-wrap,.audit-wrap {
        padding: 20px 16px;
    }
}
@media (max-width: 768px) {
    .pay-wrap,.pyo-wrap,.usr-wrap,.drv-wrap,
    .pax-wrap,.res-wrap,.rf-wrap,.rpt-wrap,
    .rv-wrap,.set-wrap,.sup-wrap,.track-wrap,.trips-wrap,
    .veh-wrap,.disp-wrap,.comm-wrap,.notif-wrap,.audit-wrap {
        padding: 12px 0;
    }
}
@media (max-width: 480px) {
    .pay-wrap,.pyo-wrap,.usr-wrap,.drv-wrap,
    .pax-wrap,.res-wrap,.rf-wrap,.rpt-wrap,
    .rv-wrap,.set-wrap,.sup-wrap,.track-wrap,.trips-wrap,
    .veh-wrap,.disp-wrap,.comm-wrap,.notif-wrap,.audit-wrap {
        padding: 8px 0;
    }
}

/* ── 2. Grilles de statistiques ─────────────────────────── */
@media (max-width: 1024px) {
    /* Tablette (sidebar cachée) : max 3 colonnes */
    .stat-grid,.stat-bar,.kpi-grid,.stats-grid {
        grid-template-columns: repeat(3,1fr);
    }
    /* Permettre aux cards de rétrécir (CSS grid gotcha) */
    .stat-card,.stat-mini,.ov-card { min-width: 0; }
}
@media (max-width: 768px) {
    .stat-grid,.stat-bar,.kpi-grid-new,.kpi-grid,.stats-grid {
        grid-template-columns: repeat(2,1fr);
        gap: 10px;
    }
}
@media (max-width: 480px) {
    .stat-grid,.stat-bar,.kpi-grid-new,.kpi-grid,.stats-grid {
        grid-template-columns: 1fr;
    }
}

/* ── 3. En-têtes de page ────────────────────────────────── */
@media (max-width: 768px) {
    .page-header,.pay-header,.rpt-header,.notif-header,
    .audit-header,.comm-header,.disp-header,.pax-header,
    .res-header,.rf-header,.rv-header,.set-header,
    .sup-header,.track-header,.trips-header,.veh-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}

/* ── 4. Barre de filtres ────────────────────────────────── */
@media (max-width: 768px) {
    .filter-bar,.filter-row {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    .filter-search {
        min-width: unset;
        width: 100%;
    }
    .filter-input  { min-width: unset; width: 100%; }
    .filter-select { width: 100%; }
    .filter-date   { width: 100%; }
    .btn-reset     { width: 100%; text-align: center; }
}

/* ── 5. Tab-bar : scroll horizontal ────────────────────── */
@media (max-width: 768px) {
    .tab-bar {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        width: 100%;
        max-width: 100%;
        flex-wrap: nowrap;
    }
    .tab-btn { flex-shrink: 0; }
}

/* ── 6. Tables : scroll horizontal (min-width géré page par page) ── */
@media (max-width: 768px) {
    .data-table-wrap,.table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        width: 100%;
    }
    .data-table { min-width: 0; }
}
@media (max-width: 480px) {
    .data-table { min-width: 0; }
}

/* ── 7. Panel drawer : plein écran mobile ───────────────── */
@media (max-width: 768px) {
    .panel-drawer { width: 100vw; max-width: 100vw; }
}
@media (max-width: 480px) {
    .panel-drawer { height: 100dvh; }
}

/* ── 8. Pagination ──────────────────────────────────────── */
@media (max-width: 768px) {
    .pag-row,.pagination-row,.table-footer {
        flex-wrap: wrap;
        gap: 6px;
    }
}

/* ── 9. Dashboard (pas de wrapper) ─────────────────────── */
@media (max-width: 768px) {
    .kpi-grid-new   { grid-template-columns: repeat(2,1fr); }
    .dash-main-grid { grid-template-columns: 1fr; }
    .fin-row        { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 480px) {
    .kpi-grid-new { grid-template-columns: 1fr; }
    .fin-row      { grid-template-columns: 1fr; }
}

/* ── 10. Rapports : grilles de graphiques ───────────────── */
@media (max-width: 768px) {
    .row-2col,.row-3col,.charts-grid { grid-template-columns: 1fr; }
    .ov-grid  { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 480px) {
    .ov-grid  { grid-template-columns: 1fr; }
}

/* ── 11. Paramètres : formulaire ────────────────────────── */
@media (max-width: 768px) {
    .set-grid { grid-template-columns: 1fr; }
}

/* ── 12. Communication : layout chat ────────────────────── */
@media (max-width: 768px) {
    .cp { width: 100%; }
}

/* ── 13. Suivi/Tracking : carte ─────────────────────────── */
@media (max-width: 768px) {
    .map-container { height: 300px; }
}

/* ── 14. Notifications : items ──────────────────────────── */
@media (max-width: 640px) {
    .notif-item { flex-direction: column; align-items: flex-start; gap: 8px; }
    .notif-actions { flex-wrap: wrap; }
}

/* ── 15. Sécurité : empêcher tout débordement global ─────── */
@media (max-width: 1024px) {
    .dash-content { max-width: 100%; overflow-x: hidden; }
    img { max-width: 100%; height: auto; }
}

/* ── 16. Stat cards : min-width:0 + taille réduite ──────── */
/* Sans min-width:0 les grid items refusent de rétrécir      */
/* en-dessous de leur contenu → débordement horizontal.      */
@media (max-width: 768px) {
    /* Autoriser les cards à rétrécir dans la grille */
    .stat-card, .stat-mini, .ov-card,
    .kpi-new, .fin-card, .kpi-card, .qa-btn { min-width: 0; }

    /* Réduire padding des cartes (2 colonnes → moins d'espace) */
    .stat-card  { padding: 12px 14px; gap: 8px; }
    .stat-mini  { padding: 10px 12px; gap: 8px; }
    .kpi-new    { padding: 14px; }
    .fin-card   { padding: 14px 16px; }

    /* Réduire font-size des valeurs pour éviter overflow */
    .stat-value      { font-size: 16px; }
    .stat-mini__val  { font-size: 16px; }
    .kpi-new__value  { font-size: 22px; }
    .fin-card__value { font-size: 18px; }
    .ov-value        { font-size: 18px; }
    .kpi-value       { font-size: 18px; }

    /* Réduire les icônes */
    .stat-icon       { width: 36px; height: 36px; font-size: 15px; }
    .stat-mini__icon { width: 32px; height: 32px; }
    .ov-icon         { width: 34px; height: 34px; font-size: 14px; }
    .kpi-new__icon   { width: 38px; height: 38px; }

    /* Réduire padding filtres et table-head */
    .filter-bar  { padding: 10px 12px; }
    .table-head  { padding: 10px 12px; }

    /* Period tabs (rapports) : scroll horizontal */
    .period-tabs { overflow-x: auto; flex-wrap: nowrap; flex-shrink: 0; }
    .period-tab  { flex-shrink: 0; }

    /* KPI new top */
    .kpi-new__top { margin-bottom: 8px; }
}
@media (max-width: 480px) {
    /* En 1 colonne il y a de la place → restaurer taille normale */
    .stat-value      { font-size: 20px; }
    .stat-mini__val  { font-size: 20px; }
    .kpi-new__value  { font-size: 26px; }
    .fin-card__value { font-size: 20px; }
    .ov-value        { font-size: 20px; }
    .kpi-value       { font-size: 20px; }
    .stat-card  { padding: 14px 16px; gap: 10px; }
    .stat-mini  { padding: 12px 14px; gap: 10px; }
    .stat-icon  { width: 40px; height: 40px; font-size: 18px; }
    .kpi-new    { padding: 16px; }
}

/* ── 17. Panel info-rows : empiler sur mobile ────────────── */
/* Les UUID/références longs débordent dans un flex row      */
@media (max-width: 640px) {
    .info-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
    }
    .info-row label { font-size: 11px; color: #9CA3AF; }
    .info-row span  { word-break: break-all; max-width: 100%; font-size: 12px; }

    .panel-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
    }
    .panel-row__value { text-align: left; word-break: break-all; }

    /* Hero montant dans panels remboursement/litige */
    .amount-hero-val { font-size: 24px; }
    .amount-hero     { padding: 14px; }
}

/* ── 18. Dashboard alert items ───────────────────────────── */
@media (max-width: 640px) {
    .alert-item { flex-wrap: wrap; gap: 8px; }
    .alert-item__link { text-align: center; flex: 1 0 100%; padding: 8px 14px; }
}

/* ── 19. Quick actions dashboard ─────────────────────────── */
@media (max-width: 480px) {
    .quick-actions { gap: 8px; }
    .qa-btn { padding: 12px 8px; gap: 6px; }
    .qa-btn__label { font-size: 10px; }
    .qa-btn__icon  { width: 34px; height: 34px; }
}

/* ── 20. Reports bar items & rev chart ───────────────────── */
@media (max-width: 480px) {
    .bar-label { min-width: 70px; font-size: 11px; }
    .rev-val   { min-width: 55px; font-size: 10px; }
    .rev-day   { min-width: 38px; font-size: 10px; }
}

/* ── 21. Panel interne : grilles doc-grid / mini-stats / info-grid ── */
@media (max-width: 640px) {
    .doc-grid   { grid-template-columns: 1fr; }
    .mini-stats { grid-template-columns: repeat(2, 1fr); }
    .info-grid  { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .mini-stats { grid-template-columns: 1fr; }
}

/* ── 22. info-label dans info-row : ne pas bloquer le retour à la ligne ── */
@media (max-width: 640px) {
    .info-label { min-width: 0; width: 100%; font-size: 11px; }
}

/* ── 23. Communication : panel-drawer + fab-wrap ─────────── */
@media (max-width: 768px) {
    .comm-wrap .panel-drawer { width: 100vw; max-width: 100vw; }
}
@media (max-width: 480px) {
    .fab-wrap { bottom: 14px; right: 14px; }
}

/* ════════════════════════════════════════════════════════════
   24. TABLES RESPONSIVES — masquage de colonnes par page
   Colonnes numérotées par position DOM (nth-child stable)
   ════════════════════════════════════════════════════════════ */

/* ── Payments (1.Payeur 2.Trajet 3.Opérateur 4.Référence 5.Montants 6.Statut 7.Date 8.Action) ── */
@media (max-width: 768px) {
    .pay-wrap .data-table th:nth-child(2),.pay-wrap .data-table td:nth-child(2),
    .pay-wrap .data-table th:nth-child(4),.pay-wrap .data-table td:nth-child(4) { display:none; }
}
@media (max-width: 480px) {
    .pay-wrap .data-table th:nth-child(3),.pay-wrap .data-table td:nth-child(3),
    .pay-wrap .data-table th:nth-child(7),.pay-wrap .data-table td:nth-child(7) { display:none; }
}

/* ── Payouts (1.Conducteur 2.Référence 3.Opérateur 4.Brut 5.Commission 6.Net 7.Trajets 8.Statut 9.Date) ── */
@media (max-width: 768px) {
    .pyo-wrap .data-table th:nth-child(2),.pyo-wrap .data-table td:nth-child(2),
    .pyo-wrap .data-table th:nth-child(3),.pyo-wrap .data-table td:nth-child(3),
    .pyo-wrap .data-table th:nth-child(7),.pyo-wrap .data-table td:nth-child(7) { display:none; }
}
@media (max-width: 480px) {
    .pyo-wrap .data-table th:nth-child(4),.pyo-wrap .data-table td:nth-child(4),
    .pyo-wrap .data-table th:nth-child(5),.pyo-wrap .data-table td:nth-child(5) { display:none; }
}

/* ── Trips (1.Route 2.Conducteur 3.Départ 4.Statut 5.Places 6.Prix/place 7.Réservations 8.Actions) ── */
@media (max-width: 768px) {
    .trips-wrap .data-table th:nth-child(3),.trips-wrap .data-table td:nth-child(3),
    .trips-wrap .data-table th:nth-child(6),.trips-wrap .data-table td:nth-child(6),
    .trips-wrap .data-table th:nth-child(7),.trips-wrap .data-table td:nth-child(7) { display:none; }
}
@media (max-width: 480px) {
    .trips-wrap .data-table th:nth-child(2),.trips-wrap .data-table td:nth-child(2),
    .trips-wrap .data-table th:nth-child(5),.trips-wrap .data-table td:nth-child(5) { display:none; }
}

/* ── Reservations (1.Passager 2.Trajet 3.Départ 4.Places 5.Montant 6.Statut 7.Paiement 8.Date) ── */
@media (max-width: 768px) {
    .res-wrap .data-table th:nth-child(3),.res-wrap .data-table td:nth-child(3),
    .res-wrap .data-table th:nth-child(4),.res-wrap .data-table td:nth-child(4),
    .res-wrap .data-table th:nth-child(7),.res-wrap .data-table td:nth-child(7) { display:none; }
}
@media (max-width: 480px) {
    .res-wrap .data-table th:nth-child(2),.res-wrap .data-table td:nth-child(2),
    .res-wrap .data-table th:nth-child(8),.res-wrap .data-table td:nth-child(8) { display:none; }
}

/* ── Users (1.Utilisateur 2.Rôle 3.KYC 4.Statut 5.Points 6.Inscription 7.Actions) ── */
@media (max-width: 768px) {
    .usr-wrap .data-table th:nth-child(5),.usr-wrap .data-table td:nth-child(5),
    .usr-wrap .data-table th:nth-child(6),.usr-wrap .data-table td:nth-child(6) { display:none; }
}
@media (max-width: 480px) {
    .usr-wrap .data-table th:nth-child(2),.usr-wrap .data-table td:nth-child(2),
    .usr-wrap .data-table th:nth-child(3),.usr-wrap .data-table td:nth-child(3) { display:none; }
}

/* ── Drivers (1.Conducteur 2.KYC 3.Véhicule 4.Statut véhicule 5.Documents 6.Inscription 7.Actions KYC) ── */
@media (max-width: 768px) {
    .drv-wrap .data-table th:nth-child(3),.drv-wrap .data-table td:nth-child(3),
    .drv-wrap .data-table th:nth-child(5),.drv-wrap .data-table td:nth-child(5) { display:none; }
}
@media (max-width: 480px) {
    .drv-wrap .data-table th:nth-child(2),.drv-wrap .data-table td:nth-child(2),
    .drv-wrap .data-table th:nth-child(6),.drv-wrap .data-table td:nth-child(6) { display:none; }
}

/* ── Passengers (1.Passager 2.Téléphone 3.Ville 4.KYC 5.Réservations 6.Note 7.Pénalités 8.Inscrit le) ── */
@media (max-width: 768px) {
    .pax-wrap .data-table th:nth-child(3),.pax-wrap .data-table td:nth-child(3),
    .pax-wrap .data-table th:nth-child(6),.pax-wrap .data-table td:nth-child(6),
    .pax-wrap .data-table th:nth-child(7),.pax-wrap .data-table td:nth-child(7) { display:none; }
}
@media (max-width: 480px) {
    .pax-wrap .data-table th:nth-child(2),.pax-wrap .data-table td:nth-child(2),
    .pax-wrap .data-table th:nth-child(8),.pax-wrap .data-table td:nth-child(8) { display:none; }
}

/* ── Vehicles (1.Véhicule 2.Type 3.Immatriculation 4.Conducteur 5.Places 6.Documents 7.Statut 8.Ajouté le) ── */
@media (max-width: 768px) {
    .veh-wrap .data-table th:nth-child(2),.veh-wrap .data-table td:nth-child(2),
    .veh-wrap .data-table th:nth-child(6),.veh-wrap .data-table td:nth-child(6),
    .veh-wrap .data-table th:nth-child(8),.veh-wrap .data-table td:nth-child(8) { display:none; }
}
@media (max-width: 480px) {
    .veh-wrap .data-table th:nth-child(3),.veh-wrap .data-table td:nth-child(3),
    .veh-wrap .data-table th:nth-child(5),.veh-wrap .data-table td:nth-child(5) { display:none; }
}

/* ── Refunds (1.Référence 2.Utilisateur 3.Montant 4.Opérateur 5.Statut 6.Date 7.Actions) ── */
@media (max-width: 768px) {
    .rf-wrap .data-table th:nth-child(1),.rf-wrap .data-table td:nth-child(1),
    .rf-wrap .data-table th:nth-child(4),.rf-wrap .data-table td:nth-child(4) { display:none; }
}
@media (max-width: 480px) {
    .rf-wrap .data-table th:nth-child(6),.rf-wrap .data-table td:nth-child(6) { display:none; }
}

/* ── Disputes (1.# 2.Requérant 3.Trajet 4.Motif 5.Statut 6.Date 7.Actions) ── */
@media (max-width: 768px) {
    .disp-wrap .data-table th:nth-child(3),.disp-wrap .data-table td:nth-child(3),
    .disp-wrap .data-table th:nth-child(6),.disp-wrap .data-table td:nth-child(6) { display:none; }
}
@media (max-width: 480px) {
    .disp-wrap .data-table th:nth-child(1),.disp-wrap .data-table td:nth-child(1),
    .disp-wrap .data-table th:nth-child(4),.disp-wrap .data-table td:nth-child(4) { display:none; }
}

/* ── Support (1.# 2.Utilisateur 3.Sujet 4.Priorité 5.Canal 6.Statut 7.Date 8.Actions) ── */
@media (max-width: 768px) {
    .sup-wrap .data-table th:nth-child(1),.sup-wrap .data-table td:nth-child(1),
    .sup-wrap .data-table th:nth-child(5),.sup-wrap .data-table td:nth-child(5),
    .sup-wrap .data-table th:nth-child(7),.sup-wrap .data-table td:nth-child(7) { display:none; }
}
@media (max-width: 480px) {
    .sup-wrap .data-table th:nth-child(4),.sup-wrap .data-table td:nth-child(4) { display:none; }
}

/* ── Reviews (1.Note 2.Auteur 3.Évalué 4.Commentaire 5.Statut 6.Date 7.Actions) ── */
@media (max-width: 768px) {
    .rv-wrap .data-table th:nth-child(3),.rv-wrap .data-table td:nth-child(3),
    .rv-wrap .data-table th:nth-child(6),.rv-wrap .data-table td:nth-child(6) { display:none; }
}
@media (max-width: 480px) {
    .rv-wrap .data-table th:nth-child(4),.rv-wrap .data-table td:nth-child(4) { display:none; }
}

/* ── Audit (1.Date/Heure 2.Sévérité 3.Action 4.Description 5.Acteur 6.IP) ── */
@media (max-width: 768px) {
    .audit-wrap .data-table th:nth-child(6),.audit-wrap .data-table td:nth-child(6) { display:none; }
}
@media (max-width: 480px) {
    .audit-wrap .data-table th:nth-child(5),.audit-wrap .data-table td:nth-child(5) { display:none; }
}

</style>
</body>
</html>
