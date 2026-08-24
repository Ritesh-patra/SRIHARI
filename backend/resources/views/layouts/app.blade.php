<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{
          sidebarOpen: false,
          collapsed: localStorage.getItem('seas_sidebar') === '1',
          profileOpen: false,
          dark: localStorage.getItem('seas_theme') === 'dark',
          toggleTheme() {
              this.dark = !this.dark;
              localStorage.setItem('seas_theme', this.dark ? 'dark' : 'light');
              document.documentElement.classList.toggle('dark', this.dark);
          },
          toggleCollapse() {
              this.collapsed = !this.collapsed;
              localStorage.setItem('seas_sidebar', this.collapsed ? '1' : '0');
          }
      }"
      x-init="document.documentElement.classList.toggle('dark', dark)"
      :class="{ 'dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0A0A0A">
    <title>{{ config('app.name', 'SEAS') }} · Admin</title>
    <script>
        (function () {
            try {
                if (localStorage.getItem('seas_theme') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/gilroy" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --al-bg: #F3F4F6;
            --al-surface: #FFFFFF;
            --al-surface-2: #F9FAFB;
            --al-text: #0A0A0A;
            --al-muted: #6B7280;
            --al-border: rgba(10, 10, 10, 0.08);
            --al-side: #FFFFFF;
            --al-side-text: #4B5563;
            --al-side-hover: #F3F4F6;
            --al-side-active-bg: #0A0A0A;
            --al-side-active-text: #FFFFFF;
            --al-header: rgba(255, 255, 255, 0.85);
            --al-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
            --al-hero: linear-gradient(120deg, #0A0A0A 0%, #1A0A0A 42%, #E10600 100%);
            --al-input: #FFFFFF;
            --al-ring: rgba(10, 10, 10, 0.06);
        }
        html.dark {
            --al-bg: #0C0C0E;
            --al-surface: #161618;
            --al-surface-2: #1E1E22;
            --al-text: #F5F5F5;
            --al-muted: #9CA3AF;
            --al-border: rgba(255, 255, 255, 0.08);
            --al-side: #111114;
            --al-side-text: #A1A1AA;
            --al-side-hover: rgba(255, 255, 255, 0.05);
            --al-side-active-bg: #E10600;
            --al-side-active-text: #FFFFFF;
            --al-header: rgba(17, 17, 20, 0.9);
            --al-shadow: 0 8px 28px rgba(0, 0, 0, 0.35);
            --al-hero: linear-gradient(120deg, #000000 0%, #2A0808 48%, #E10600 100%);
            --al-input: #1E1E22;
            --al-ring: rgba(255, 255, 255, 0.06);
        }
        body.admin-shell {
            font-family: Inter, system-ui, -apple-system, sans-serif;
            color: var(--al-text);
            background: var(--al-bg);
            min-height: 100dvh;
        }
        .al-display { font-family: Gilroy, Inter, system-ui, sans-serif; letter-spacing: -0.02em; font-weight: 700; }
        .al-side {
            width: min(16.75rem, 88vw);
            background: var(--al-side);
            border-right: 1px solid var(--al-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 50;
            transform: translateX(-100%);
            transition: transform .3s cubic-bezier(.22,1,.36,1), width .25s ease;
        }
        @media (min-width: 1024px) {
            .al-side { position: sticky; top: 0; height: 100dvh; width: 16.75rem; transform: none; }
            .al-side.collapsed { width: 4.75rem; }
            .al-side.collapsed .al-label,
            .al-side.collapsed .al-section,
            .al-side.collapsed .al-brand-text,
            .al-side.collapsed .al-note { display: none; }
            .al-side.collapsed .al-link { justify-content: center; padding-inline: .65rem; }
        }
        @media (min-width: 1536px) {
            .al-side:not(.collapsed) { width: 17.5rem; }
        }
        .al-side.open { transform: translateX(0); }
        .al-link {
            display: flex;
            align-items: center;
            gap: .7rem;
            padding: .7rem .85rem;
            border-radius: 12px;
            color: var(--al-side-text);
            font-size: .875rem;
            font-weight: 600;
            transition: .15s ease;
        }
        .al-link:hover { background: var(--al-side-hover); color: var(--al-text); }
        .al-link.active {
            background: var(--al-side-active-bg);
            color: var(--al-side-active-text);
        }
        .al-link.active svg { color: #fff; }
        html:not(.dark) .al-link.active svg { color: #E10600; }
        html:not(.dark) .al-link.active { box-shadow: var(--al-shadow); }
        .al-section {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--al-muted);
            padding: 1rem .85rem .35rem;
        }
        .al-header {
            position: sticky;
            top: 0;
            z-index: 30;
            background: var(--al-header);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--al-border);
        }
        .al-panel, .al-card {
            background: var(--al-surface);
            border: 1px solid var(--al-border);
            border-radius: 18px;
            box-shadow: var(--al-shadow);
            color: var(--al-text);
        }
        .al-hero {
            background: var(--al-hero);
            border-radius: 22px;
            color: #fff;
            box-shadow: var(--al-shadow);
            position: relative;
            overflow: hidden;
        }
        .al-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 90% 20%, rgba(255,255,255,.12), transparent 40%);
            pointer-events: none;
        }
        .al-input {
            width: 100%;
            border-radius: 14px;
            border: 1px solid var(--al-border);
            background: var(--al-input);
            color: var(--al-text);
            padding: .7rem 1rem;
            font-size: .875rem;
        }
        .al-input:focus {
            outline: none;
            border-color: #E10600;
            box-shadow: 0 0 0 3px rgba(225, 6, 0, 0.15);
        }
        .al-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            border-radius: 12px;
            padding: .65rem 1.1rem;
            font-size: .8125rem;
            font-weight: 700;
            transition: .15s ease;
            text-decoration: none;
            line-height: 1.2;
        }
        .al-btn-primary { background: #E10600 !important; color: #fff !important; }
        .al-btn-primary:hover { background: #B10500 !important; color: #fff !important; }
        .al-btn-ink { background: #0A0A0A !important; color: #fff !important; }
        .al-btn-ink:hover { background: #1A1A1A !important; color: #fff !important; opacity: 1; }
        html.dark .al-btn-ink { background: #FFFFFF !important; color: #0A0A0A !important; }
        html.dark .al-btn-ink:hover { background: #F3F4F6 !important; color: #0A0A0A !important; }
        .al-btn-ghost {
            background: var(--al-surface);
            color: var(--al-text);
            border: 1px solid var(--al-border);
        }
        /* White CTA — always light fill + dark text (hero / dark mode safe) */
        .al-btn-light {
            background: #FFFFFF !important;
            color: #0A0A0A !important;
            border: 1px solid rgba(255, 255, 255, 0.35);
        }
        .al-btn-light:hover { background: #F3F4F6 !important; color: #0A0A0A !important; }
        /* Glass CTA on red/dark hero */
        .al-btn-glass {
            background: rgba(255, 255, 255, 0.12) !important;
            color: #FFFFFF !important;
            border: 1px solid rgba(255, 255, 255, 0.35);
        }
        .al-btn-glass:hover { background: rgba(255, 255, 255, 0.2) !important; color: #FFFFFF !important; }
        /* Hero CTAs / chips must stay readable on red banner */
        .al-hero .al-btn-light,
        .al-hero a.al-btn-light,
        .al-hero button.al-btn-light {
            background: #FFFFFF !important;
            color: #0A0A0A !important;
        }
        .al-hero .al-btn-glass,
        .al-hero a.al-btn-glass {
            background: rgba(255, 255, 255, 0.12) !important;
            color: #FFFFFF !important;
        }
        .al-hero button.bg-white,
        .al-hero a.bg-white {
            background: #FFFFFF !important;
            color: #0A0A0A !important;
        }
        .al-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border-radius: 999px;
            padding: .25rem .7rem;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .al-content { padding: 1.1rem 1rem 2.5rem; }
        @media (min-width: 640px) { .al-content { padding: 1.35rem 1.35rem 2.75rem; } }
        @media (min-width: 1024px) { .al-content { padding: 1.5rem 1.75rem 3rem; } }
        @media (min-width: 1280px) { .al-content { padding: 1.75rem 2.25rem 3rem; } }
        .al-content > .mx-auto { width: 100%; }
        @media (min-width: 1920px) { .al-content > .mx-auto { max-width: 1680px; } }
        .al-icon-btn {
            display: inline-flex;
            height: 2.75rem;
            width: 2.75rem;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #FFFFFF;
            border: 1px solid rgba(10, 10, 10, 0.1);
            color: #0A0A0A;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        html.dark .al-icon-btn {
            background: #FFFFFF;
            border-color: rgba(255, 255, 255, 0.2);
            color: #0A0A0A;
        }
        .al-icon-btn:hover { background: #F3F4F6; }
        .al-icon-btn svg { width: 1.35rem; height: 1.35rem; }
        .al-link svg { width: 1.25rem; height: 1.25rem; stroke-width: 2; }

        /* Site-wide scrollbars */
        * { scrollbar-width: thin; scrollbar-color: #E10600 transparent; }
        *::-webkit-scrollbar { width: 10px; height: 10px; }
        *::-webkit-scrollbar-track { background: transparent; }
        *::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #E10600, #8B0500);
            border-radius: 999px;
            border: 2px solid transparent;
            background-clip: padding-box;
        }
        *::-webkit-scrollbar-thumb:hover { background: #FF2A22; background-clip: padding-box; border: 2px solid transparent; }
        html.dark * { scrollbar-color: #E10600 #1A1A1C; }
        html.dark *::-webkit-scrollbar-track { background: #111114; }
        .al-search {
            display: none;
            align-items: center;
            gap: .6rem;
            flex: 1;
            max-width: 28rem;
            border-radius: 999px;
            background: var(--al-surface);
            border: 1px solid var(--al-border);
            padding: .55rem 1rem;
            box-shadow: var(--al-shadow);
        }
        @media (min-width: 768px) { .al-search { display: flex; } }
        .al-search input {
            width: 100%;
            border: 0;
            background: transparent;
            color: var(--al-text);
            font-size: .875rem;
            outline: none;
        }
        .al-table wrap, .al-table { width: 100%; }
        .al-table th {
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--al-muted);
            padding: .9rem 1rem;
            border-bottom: 1px solid var(--al-border);
        }
        .al-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--al-border);
            font-size: .875rem;
            vertical-align: middle;
        }
        .al-table tr:last-child td { border-bottom: 0; }
        /* High-contrast body text — explicit dark/light so vars can't leave ink on black */
        .text-theme { color: var(--al-text) !important; }
        .text-muted { color: var(--al-muted) !important; }
        html:not(.dark) .text-theme { color: #0A0A0A !important; }
        html:not(.dark) .text-muted { color: #6B7280 !important; }
        html.dark .text-theme { color: #F5F5F5 !important; }
        html.dark .text-muted { color: #A1A1AA !important; }
        .bg-surface { background: var(--al-surface); }

        /* Dark mode: fix legacy seas-* pages (Analytics, Hierarchy, Masters, Ops) */
        html.dark .seas-card,
        html.dark .seas-card-pad,
        html.dark .seas-stat,
        html.dark .seas-module,
        html.dark .seas-table-wrap {
            background: var(--al-surface) !important;
            border-color: var(--al-border) !important;
            color: var(--al-text) !important;
            box-shadow: var(--al-shadow) !important;
            ring-color: transparent;
        }
        /* Keep secondary/action buttons light (not dark fills) */
        html.dark .seas-btn-secondary,
        html.dark .al-btn-ghost {
            background: #FFFFFF !important;
            color: #0A0A0A !important;
            border-color: rgba(255,255,255,0.25) !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.2) !important;
        }
        html.dark .seas-btn-secondary:hover,
        html.dark .al-btn-ghost:hover {
            background: #F3F4F6 !important;
            color: #0A0A0A !important;
        }
        html.dark .seas-btn-primary,
        .seas-btn-primary {
            background: #E10600 !important;
            color: #FFFFFF !important;
            box-shadow: none !important;
        }
        html.dark .seas-btn-primary:hover,
        .seas-btn-primary:hover {
            background: #B10500 !important;
            color: #FFFFFF !important;
        }
        html.dark .seas-btn-success {
            background: #FFFFFF !important;
            color: #0A0A0A !important;
        }
        /* Never let global dark bg remaps crush button contrast */
        html.dark .al-btn.bg-white,
        html.dark a.al-btn.bg-white,
        html.dark button.al-btn.bg-white,
        html.dark .al-btn-light,
        html.dark a.rounded-xl.bg-white.font-bold,
        html.dark button.rounded-xl.bg-white.font-bold {
            background-color: #FFFFFF !important;
            color: #0A0A0A !important;
        }
        html.dark .al-btn.text-seas-950,
        html.dark .al-btn .text-seas-950 {
            color: #0A0A0A !important;
        }
        html.dark .seas-input,
        html.dark select.seas-input,
        html.dark input.seas-input,
        html.dark textarea.seas-input {
            background: var(--al-input) !important;
            border-color: var(--al-border) !important;
            color: var(--al-text) !important;
            box-shadow: none !important;
        }
        html.dark .seas-input::placeholder { color: #6B7280 !important; }
        html.dark .seas-alert-success {
            background: rgba(6, 78, 59, 0.35) !important;
            border-color: rgba(16, 185, 129, 0.35) !important;
            color: #6EE7B7 !important;
        }
        html.dark .seas-alert-error {
            background: rgba(127, 29, 29, 0.35) !important;
            border-color: rgba(248, 113, 113, 0.35) !important;
            color: #FCA5A5 !important;
        }
        html.dark .seas-title,
        html.dark .font-display.text-seas-900,
        html.dark .text-seas-900,
        html.dark .text-seas-800,
        html.dark .text-seas-700 {
            color: #F5F5F5 !important;
        }
        /* Panels: default readable ink in dark (text-theme / muted keep !important overrides) */
        html.dark .al-panel,
        html.dark .al-card {
            color: #F5F5F5;
        }
        html.dark .text-seas-600,
        html.dark .text-seas-500 {
            color: #A1A1AA !important;
        }
        html.dark .text-seas-400 { color: #9CA3AF !important; }
        /* Remap white surfaces in dark mode — but never buttons / CTAs */
        html.dark .bg-white:not(.al-btn):not(button):not(.al-btn-light):not(.al-btn-primary):not(.al-btn-ink):not(.seas-btn-primary):not(.seas-btn-secondary):not(.seas-btn-success),
        html.dark .bg-white\/70:not(.al-btn):not(button),
        html.dark .bg-white\/80:not(.al-btn):not(button) {
            background-color: var(--al-surface) !important;
        }
        html.dark .bg-canvas-soft,
        html.dark .bg-canvas-soft\/60,
        html.dark .bg-canvas-soft\/80,
        html.dark .bg-seas-50,
        html.dark .bg-seas-100 {
            background-color: var(--al-surface-2) !important;
        }
        html.dark .border-seas-100,
        html.dark .border-seas-200,
        html.dark .border-white,
        html.dark .border-white\/80 {
            border-color: var(--al-border) !important;
        }
        html.dark .divide-seas-100 > :not([hidden]) ~ :not([hidden]) {
            border-color: var(--al-border) !important;
        }
        html.dark .hover\:bg-seas-50:hover,
        html.dark .hover\:bg-canvas-soft\/80:hover,
        html.dark .hover\:bg-canvas-soft:hover {
            background-color: var(--al-surface-2) !important;
        }
        html.dark .seas-table-wrap table tbody tr {
            border-color: var(--al-border) !important;
        }
        html.dark .seas-table-wrap table tbody tr:hover {
            background-color: var(--al-surface-2) !important;
        }
        html.dark .volt-soft,
        html.dark .bg-volt-soft {
            background-color: rgba(225, 6, 0, 0.18) !important;
            color: #FF8A80 !important;
        }
        html.dark .text-volt-deep { color: #FF8A80 !important; }
        html.dark main.al-content .rounded-xl.bg-seas-50,
        html.dark main.al-content .rounded-2xl.bg-seas-50 {
            background: var(--al-surface-2) !important;
            color: var(--al-text);
        }
        html.dark main.al-content strong.text-seas-900 { color: #fff !important; }
        html.dark .shadow-sm,
        html.dark .shadow-card,
        html.dark .shadow-seas,
        html.dark .shadow-seas-lg { box-shadow: var(--al-shadow) !important; }
    </style>
</head>
<body class="admin-shell antialiased">
@php
    $unread = \App\Models\AppNotification::where('user_id', auth()->id())->whereNull('read_at')->count();
@endphp

<div class="flex min-h-screen">
    <aside class="al-side" :class="{ open: sidebarOpen, collapsed: collapsed }">
        <div class="flex items-center gap-3 border-b px-4 py-5" style="border-color: var(--al-border)">
            <a href="{{ route('dashboard') }}" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-volt text-white">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 4 14h7l-1 8 10-14h-7l1-6Z"/></svg>
            </a>
            <div class="al-brand-text min-w-0">
                <div class="al-display text-xl font-extrabold tracking-tight text-theme">SEAS</div>
                <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">Admin</div>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-2.5 py-3">
            <p class="al-section">Overview</p>
            @foreach([
                ['dashboard', 'dashboard', 'Dashboard', 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z'],
                ['reports.surveyors', 'reports.surveyors*', 'Audit Report', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9l2 2 4-4'],
                ['reports.index', 'reports.index', 'Analytics', 'M4 19h16M7 16V9M12 16V5M17 16v-7'],
                ['report-analysis.index', 'report-analysis.*', 'Report Analysis', 'M9 17v-6M13 17V7M17 17v-3M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2z'],
                ['reading-uploads.index', 'reading-uploads.*', 'Reading Upload', 'M12 16V4m0 0L8 8m4-4 4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'],
            ] as [$route, $match, $label, $icon])
                <a href="{{ route($route) }}" class="al-link {{ request()->routeIs($match) ? 'active' : '' }}">
                    <svg class="shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    <span class="al-label">{{ $label }}</span>
                </a>
            @endforeach

            <p class="al-section">Organization</p>
            @foreach([
                ['admin.users', 'admin.users*', 'Users & Roles', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
                ['admin.hierarchy', 'admin.hierarchy', 'Hierarchy', 'M6 3h12v4H6zM9 11h6v4H9zM4 19h6v2H4zM14 19h6v2h-6zM12 7v4M7 15v4M17 15v4'],
                ['admin.masters', 'admin.masters*', 'Master Data', 'M4 6h16M4 12h16M4 18h10'],
                ['admin.poles', 'admin.poles*', 'Field Poles', 'M12 2v3M8 5h8l1.2 4.5H6.8L8 5zm-1.2 4.5.9 12.5h8.6l.9-12.5M10 22h4'],
            ] as [$route, $match, $label, $icon])
                <a href="{{ route($route) }}" class="al-link {{ request()->routeIs($match) ? 'active' : '' }}">
                    <svg class="shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    <span class="al-label">{{ $label }}</span>
                </a>
            @endforeach

            <p class="al-section">Operations</p>
            @foreach([
                ['consumer-approval.index', 'consumer-approval.*', 'Consumer Approval', 'M9 12l2 2 4-4M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z'],
                ['substation-surveys.index', 'substation-surveys.*', 'Substation Survey', 'M4 20h16M6 20V10l6-5 6 5v10M10 20v-5h4v5'],
                ['feeder-surveys.index', 'feeder-surveys.*', 'Feeder Surveys', 'M4 6h16M4 12h10M4 18h14'],
                ['dtr-surveys.index', 'dtr-surveys.*', 'DTR Surveys', 'M13 10V3L4 14h7v7l9-11h-7z'],
                ['dtr-mapping-corrections.index', 'dtr-mapping-corrections.*', 'Mapping Corrections', 'M8 7h12M8 12h12M8 17h8M4 7h.01M4 12h.01M4 17h.01'],
                ['dtr-reactivation.index', 'dtr-reactivation.*', 'DTR Re-activation', 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
                ['pole-surveys.index', 'pole-surveys.*', 'Pole Surveys', 'M12 2v3M8 5h8l1.2 4.5H6.8L8 5zm-1.2 4.5.9 12.5h8.6l.9-12.5M10 22h4'],
                ['admin.activity', 'admin.activity', 'Audit Logs', 'M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z'],
                ['notifications.index', 'notifications.*', 'Notifications', 'M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0'],
                ['admin.settings', 'admin.settings', 'Settings', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.6.9 1 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z'],
            ] as [$route, $match, $label, $icon])
                <a href="{{ route($route) }}" class="al-link {{ request()->routeIs($match) ? 'active' : '' }}">
                    <svg class="shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    <span class="al-label flex-1">{{ $label }}</span>
                    @if($route === 'notifications.index' && $unread)
                        <span class="rounded-md bg-volt px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $unread }}</span>
                    @endif
                </a>
            @endforeach

            <p class="al-note mx-2 mt-8 text-[11px] leading-relaxed text-muted">
                Field teams use the Flutter app.
            </p>
        </nav>

        <div class="border-t p-2" style="border-color: var(--al-border)">
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="al-link w-full text-left">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    <span class="al-label">Logout</span>
                </button>
            </form>
        </div>
    </aside>

    <div x-show="sidebarOpen" @click="sidebarOpen=false" class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden" x-cloak></div>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="al-header">
            <div class="flex h-[4.25rem] items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    {{-- Single panel control (replaces dual hamburger buttons) --}}
                    <button type="button"
                            @click="window.innerWidth < 1024 ? sidebarOpen = true : toggleCollapse()"
                            class="al-icon-btn"
                            title="Toggle menu">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="4" width="18" height="16" rx="2"/>
                            <path d="M9 4v16"/>
                        </svg>
                    </button>
                    <div class="min-w-0 hidden sm:block">
                        <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-volt">SEAS</div>
                        <div class="al-display truncate text-sm font-bold text-theme">Admin Console</div>
                    </div>
                </div>

                <div class="al-search">
                    <svg class="h-4 w-4 shrink-0 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
                    <input type="search" placeholder="Search users, DTRs, reports…">
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="hidden items-center gap-2 text-sm text-muted xl:flex">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                        <span>{{ now()->format('d M Y, l') }}</span>
                    </div>

                    <button type="button" @click="toggleTheme()" class="al-icon-btn" :title="dark ? 'Light mode' : 'Dark mode'">
                        <svg x-show="!dark" class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/></svg>
                        <svg x-show="dark" x-cloak class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    </button>

                    <a href="{{ route('notifications.index') }}" class="al-icon-btn relative" title="Notifications">
                        {{-- Inbox-style notification icon --}}
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8 6 8-6"/>
                        </svg>
                        @if($unread)<span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-volt px-1 text-[9px] font-bold text-white">{{ min($unread,9) }}</span>@endif
                    </a>

                    <div class="relative" @click.outside="profileOpen=false">
                        <button type="button" @click="profileOpen=!profileOpen" class="flex items-center gap-2 rounded-full border py-1 pl-1 pr-2 sm:pr-3" style="border-color: var(--al-border); background: var(--al-surface)">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-seas-950 text-xs font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            <span class="hidden text-left sm:block">
                                <span class="block max-w-[110px] truncate text-xs font-bold text-theme">{{ auth()->user()->name }}</span>
                            </span>
                            <svg class="hidden h-3.5 w-3.5 text-muted sm:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div x-show="profileOpen" x-cloak x-transition class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border shadow-seas" style="background: var(--al-surface); border-color: var(--al-border)">
                            <div class="border-b px-4 py-3" style="border-color: var(--al-border)">
                                <div class="truncate text-sm font-bold text-theme">{{ auth()->user()->name }}</div>
                                <div class="truncate text-xs text-muted">{{ auth()->user()->email }}</div>
                            </div>
                            <div class="p-1.5 text-sm">
                                <a href="{{ route('profile.edit') }}" class="block rounded-xl px-3 py-2.5 font-semibold text-theme hover:opacity-80">Profile</a>
                                <a href="{{ route('admin.settings') }}" class="block rounded-xl px-3 py-2.5 font-semibold text-theme hover:opacity-80">Settings</a>
                                <button type="button" @click="toggleTheme()" class="w-full rounded-xl px-3 py-2.5 text-left font-semibold text-theme hover:opacity-80" x-text="dark ? 'Switch to Light' : 'Switch to Dark'"></button>
                            </div>
                            <div class="border-t p-1.5" style="border-color: var(--al-border)">
                                <form method="POST" action="{{ route('logout') }}">@csrf
                                    <button class="w-full rounded-xl px-3 py-2.5 text-left text-sm font-bold text-volt">Logout</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="al-content">{{ $slot }}</main>
    </div>
</div>
@stack('scripts')
</body>
</html>
