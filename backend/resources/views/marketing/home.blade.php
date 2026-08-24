<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SEAS — Srihari Energy Audit System. Premium DTR-to-consumer field survey platform.">
    <title>SEAS · Field Intelligence Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #050505;
            --bg-2: #0D0D0D;
            --card: #121212;
            --red: #E50914;
            --red-2: #FF2D2D;
            --white: #FFFFFF;
            --gray: #D9D9D9;
            --muted: #9E9E9E;
            --border: rgba(255,255,255,0.08);
            --glow: rgba(229,9,20,0.35);
            --radius-card: 20px;
            --radius-btn: 14px;
            --pad: clamp(72px, 12vw, 120px);
            --max: 1200px;
            --ease: cubic-bezier(0.22, 1, 0.36, 1);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: Inter, system-ui, sans-serif;
            background: var(--bg);
            color: var(--white);
            line-height: 1.6;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
        img, svg { display: block; max-width: 100%; }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; cursor: pointer; border: 0; background: none; color: inherit; }

        .wrap { width: min(100% - 40px, var(--max)); margin-inline: auto; }
        .eyebrow {
            font-family: Poppins, sans-serif;
            font-size: 12px; font-weight: 600; letter-spacing: 0.18em;
            text-transform: uppercase; color: var(--red);
        }
        h1, h2, h3 { font-family: Poppins, sans-serif; letter-spacing: -0.03em; line-height: 1.08; }
        .muted { color: var(--muted); }
        .gray { color: var(--gray); }

        /* Ambient */
        .ambient {
            position: fixed; inset: 0; pointer-events: none; z-index: 0;
            background:
                radial-gradient(ellipse 50% 40% at 80% -10%, rgba(229,9,20,0.22), transparent 55%),
                radial-gradient(ellipse 40% 35% at 10% 20%, rgba(255,45,45,0.08), transparent 50%),
                linear-gradient(#050505, #050505);
        }
        .ambient::after {
            content: '';
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: radial-gradient(ellipse at center, black 20%, transparent 75%);
            opacity: 0.5;
        }

        /* Nav */
        .nav {
            position: sticky; top: 0; z-index: 50;
            backdrop-filter: blur(18px) saturate(140%);
            background: rgba(5,5,5,0.72);
            border-bottom: 1px solid transparent;
            transition: border-color .3s var(--ease), background .3s;
        }
        .nav.scrolled { border-bottom-color: var(--border); background: rgba(5,5,5,0.88); }
        .nav-inner {
            width: min(100% - 40px, var(--max));
            margin: 0 auto;
            height: 72px;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 16px;
        }
        .logo {
            display: inline-flex; align-items: center; gap: 10px;
            font-family: Poppins, sans-serif; font-weight: 700; font-size: 18px; letter-spacing: -0.02em;
        }
        .logo-mark {
            width: 34px; height: 34px; border-radius: 10px;
            background: linear-gradient(145deg, var(--red), #8B0000);
            display: grid; place-items: center;
            box-shadow: 0 0 24px var(--glow);
        }
        .logo-mark svg { width: 18px; height: 18px; }
        .nav-links {
            display: flex; gap: 28px;
            font-size: 14px; font-weight: 500; color: var(--muted);
            justify-content: center;
        }
        .nav-links a { transition: color .2s; }
        .nav-links a:hover { color: var(--white); }
        .nav-cta { justify-self: end; display: flex; gap: 10px; align-items: center; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            height: 48px; padding: 0 22px; border-radius: var(--radius-btn);
            font-family: Poppins, sans-serif; font-weight: 600; font-size: 14px;
            transition: transform .25s var(--ease), box-shadow .25s, background .25s, border-color .25s;
        }
        .btn-primary {
            background: var(--red); color: var(--white);
            box-shadow: 0 8px 28px rgba(229,9,20,0.28);
        }
        .btn-primary:hover {
            transform: scale(1.03);
            background: var(--red-2);
            box-shadow: 0 12px 40px var(--glow);
        }
        .btn-ghost {
            background: transparent; color: var(--white);
            border: 1px solid rgba(255,255,255,0.22);
        }
        .btn-ghost:hover {
            transform: scale(1.03);
            border-color: rgba(255,255,255,0.45);
            box-shadow: 0 0 0 4px rgba(255,255,255,0.04);
        }
        .btn-sm { height: 40px; padding: 0 16px; font-size: 13px; }

        /* Reveal */
        .reveal {
            opacity: 0; transform: translateY(28px);
            transition: opacity .8s var(--ease), transform .8s var(--ease);
        }
        .reveal.in { opacity: 1; transform: none; }
        .reveal-d1 { transition-delay: .08s; }
        .reveal-d2 { transition-delay: .16s; }
        .reveal-d3 { transition-delay: .24s; }

        /* Hero */
        .hero {
            position: relative; z-index: 1;
            padding: 56px 0 var(--pad);
            min-height: calc(100dvh - 72px);
            display: flex; align-items: center;
        }
        .hero-grid {
            display: grid; gap: 48px; align-items: center;
        }
        @media (min-width: 980px) {
            .hero-grid { grid-template-columns: 1.05fr 0.95fr; gap: 56px; }
        }
        .hero h1 {
            margin-top: 18px;
            font-size: clamp(2.6rem, 6vw, 4.6rem);
            font-weight: 800;
            max-width: 11ch;
        }
        .hero h1 span {
            background: linear-gradient(105deg, #fff 40%, #FF8A8A 100%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .hero-lead {
            margin-top: 20px; max-width: 36ch;
            font-size: clamp(1rem, 1.5vw, 1.15rem); color: var(--muted); font-weight: 400;
        }
        .hero-actions { margin-top: 32px; display: flex; flex-wrap: wrap; gap: 12px; }

        /* Dashboard mock — full visual plane */
        .dash {
            position: relative;
            border-radius: 24px;
            background: linear-gradient(160deg, #161616, #0A0A0A 60%, #1A0808);
            border: 1px solid var(--border);
            box-shadow:
                0 40px 80px rgba(0,0,0,0.55),
                0 0 0 1px rgba(255,255,255,0.04) inset,
                0 0 80px rgba(229,9,20,0.12);
            overflow: hidden;
            transform: perspective(1200px) rotateY(-6deg) rotateX(4deg);
            transition: transform .6s var(--ease);
        }
        .dash:hover { transform: perspective(1200px) rotateY(-2deg) rotateX(1deg) scale(1.01); }
        .dash-glow {
            position: absolute; width: 280px; height: 280px; border-radius: 50%;
            background: radial-gradient(circle, rgba(229,9,20,0.45), transparent 70%);
            filter: blur(20px); top: -80px; right: -40px; pointer-events: none;
            animation: float 7s ease-in-out infinite;
        }
        .dash-orb {
            position: absolute; width: 120px; height: 120px; border-radius: 50%;
            border: 1px solid rgba(229,9,20,0.35);
            bottom: 18%; left: -30px; opacity: .5;
            animation: float 5.5s ease-in-out infinite reverse;
        }
        .dash-top {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 18px; border-bottom: 1px solid var(--border);
            background: rgba(255,255,255,0.02);
        }
        .dots { display: flex; gap: 6px; }
        .dots span { width: 8px; height: 8px; border-radius: 50%; background: #2A2A2A; }
        .dots span:first-child { background: var(--red); }
        .dash-body { padding: 18px; display: grid; gap: 14px; }
        .dash-kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .kpi {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 14px; padding: 12px;
        }
        .kpi b {
            display: block; font-family: Poppins, sans-serif;
            font-size: 1.35rem; font-weight: 700; letter-spacing: -0.03em;
        }
        .kpi span { font-size: 11px; color: var(--muted); }
        .dash-main { display: grid; grid-template-columns: 1.4fr 1fr; gap: 10px; }
        .panel {
            background: rgba(255,255,255,0.025);
            border: 1px solid var(--border);
            border-radius: 16px; padding: 14px; min-height: 160px;
        }
        .panel h4 { font-family: Poppins, sans-serif; font-size: 13px; font-weight: 600; margin-bottom: 12px; }
        .chart {
            height: 100px; display: flex; align-items: flex-end; gap: 6px;
        }
        .chart i {
            flex: 1; border-radius: 6px 6px 2px 2px;
            background: linear-gradient(180deg, var(--red-2), rgba(229,9,20,0.2));
            animation: grow .9s var(--ease) both;
        }
        .ring-wrap { display: grid; place-items: center; height: 110px; }
        .ring {
            width: 96px; height: 96px; border-radius: 50%;
            background:
                radial-gradient(circle at center, #121212 58%, transparent 59%),
                conic-gradient(var(--red) 0 72%, #222 72%);
            display: grid; place-items: center;
            box-shadow: 0 0 28px rgba(229,9,20,0.25);
        }
        .ring strong { font-family: Poppins, sans-serif; font-size: 1.25rem; }

        /* Sections */
        section.block { position: relative; z-index: 1; padding: var(--pad) 0; }
        .sec-head { max-width: 560px; margin-bottom: 48px; }
        .sec-head h2 { margin-top: 14px; font-size: clamp(2rem, 4vw, 3rem); font-weight: 700; }
        .sec-head p { margin-top: 14px; color: var(--muted); font-size: 1.05rem; }

        .features {
            display: grid; gap: 16px;
            grid-template-columns: 1fr;
        }
        @media (min-width: 720px) { .features { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1000px) { .features { grid-template-columns: repeat(3, 1fr); } }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            padding: 28px;
            transition: transform .35s var(--ease), box-shadow .35s, border-color .35s;
            position: relative; overflow: hidden;
        }
        .card::before {
            content: ''; position: absolute; inset: auto -20% -40% auto; width: 160px; height: 160px;
            background: radial-gradient(circle, rgba(229,9,20,0.18), transparent 70%);
            pointer-events: none;
        }
        .card:hover {
            transform: translateY(-6px) scale(1.01);
            border-color: rgba(229,9,20,0.28);
            box-shadow: 0 24px 48px rgba(0,0,0,0.35), 0 0 40px rgba(229,9,20,0.12);
        }
        .card-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: grid; place-items: center;
            background: rgba(229,9,20,0.12); color: var(--red-2);
            margin-bottom: 18px; border: 1px solid rgba(229,9,20,0.2);
        }
        .card h3 { font-size: 1.15rem; font-weight: 650; margin-bottom: 8px; }
        .card p { color: var(--muted); font-size: 0.95rem; }

        /* Stats band */
        .stats {
            background: linear-gradient(180deg, #0D0D0D, #090909);
            border-block: 1px solid var(--border);
        }
        .stats-grid {
            display: grid; gap: 24px;
            grid-template-columns: repeat(2, 1fr);
        }
        @media (min-width: 800px) { .stats-grid { grid-template-columns: repeat(4, 1fr); } }
        .stat { text-align: center; padding: 12px; }
        .stat b {
            display: block; font-family: Poppins, sans-serif;
            font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; letter-spacing: -0.04em;
        }
        .stat span { color: var(--muted); font-size: 13px; letter-spacing: 0.04em; text-transform: uppercase; }

        /* Process */
        .process {
            display: grid; gap: 0; counter-reset: step;
        }
        @media (min-width: 900px) {
            .process { grid-template-columns: repeat(4, 1fr); }
        }
        .step {
            padding: 28px 24px;
            border: 1px solid var(--border);
            background: rgba(18,18,18,0.8);
            position: relative;
        }
        .step:first-child { border-radius: 20px 0 0 20px; }
        .step:last-child { border-radius: 0 20px 20px 0; }
        @media (max-width: 899px) {
            .step { border-radius: 16px !important; margin-bottom: 10px; }
        }
        .step::before {
            counter-increment: step;
            content: counter(step, decimal-leading-zero);
            font-family: Poppins, sans-serif;
            font-size: 2rem; font-weight: 800; color: rgba(229,9,20,0.85);
            display: block; margin-bottom: 16px; letter-spacing: -0.04em;
        }
        .step h3 { font-size: 1.05rem; margin-bottom: 8px; }
        .step p { color: var(--muted); font-size: 0.92rem; }

        /* Split */
        .split {
            display: grid; gap: 40px; align-items: center;
        }
        @media (min-width: 900px) { .split { grid-template-columns: 1fr 1fr; gap: 64px; } }
        .glass {
            border-radius: 24px;
            background: linear-gradient(145deg, rgba(255,255,255,0.04), rgba(255,255,255,0.01));
            border: 1px solid var(--border);
            backdrop-filter: blur(12px);
            padding: 28px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.35);
        }
        .row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 0; border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        .row:last-child { border-bottom: 0; }
        .pill {
            font-size: 11px; font-weight: 600; letter-spacing: 0.04em;
            padding: 4px 10px; border-radius: 999px;
            background: rgba(229,9,20,0.15); color: #FF8A8A;
        }
        .pill.ok { background: rgba(16,185,129,0.12); color: #6EE7B7; }

        /* CTA */
        .cta {
            position: relative; overflow: hidden;
            border-radius: 28px;
            background: linear-gradient(135deg, #140808, #0A0A0A 45%, #1A0505);
            border: 1px solid rgba(229,9,20,0.25);
            padding: clamp(40px, 6vw, 72px);
            text-align: center;
            box-shadow: 0 0 80px rgba(229,9,20,0.12);
        }
        .cta::before {
            content: ''; position: absolute; inset: -20%;
            background: radial-gradient(circle at 50% 0%, rgba(229,9,20,0.28), transparent 45%);
            pointer-events: none;
        }
        .cta h2 { position: relative; font-size: clamp(2rem, 4vw, 3.2rem); font-weight: 800; }
        .cta p { position: relative; margin: 14px auto 28px; max-width: 42ch; color: var(--muted); }
        .cta .hero-actions { position: relative; justify-content: center; }

        /* Footer */
        footer {
            position: relative; z-index: 1;
            border-top: 1px solid var(--border);
            padding: 48px 0 32px;
            background: #070707;
        }
        .foot {
            display: flex; flex-wrap: wrap; gap: 24px;
            justify-content: space-between; align-items: flex-start;
        }
        .foot nav { display: flex; flex-wrap: wrap; gap: 18px; color: var(--muted); font-size: 14px; }
        .foot nav a:hover { color: var(--white); }
        .copy { margin-top: 32px; color: #666; font-size: 13px; }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-14px); }
        }
        @keyframes grow {
            from { transform: scaleY(0.2); opacity: .4; }
            to { transform: scaleY(1); opacity: 1; }
        }

        @media (max-width: 979px) {
            .nav-inner { gap: 10px; }
            .nav-links { gap: 14px; font-size: 13px; }
            .dash { transform: none; }
            .dash:hover { transform: none; }
        }
    </style>
</head>
<body>
    <div class="ambient" aria-hidden="true"></div>

    <header class="nav" id="nav">
        <div class="nav-inner">
            <a class="logo" href="#top">
                <span class="logo-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2"><path d="M13 2 4 14h7l-1 8 10-14h-7l0-6z"/></svg>
                </span>
                SEAS
            </a>
            <nav class="nav-links" aria-label="Primary">
                <a href="#platform">Platform</a>
                <a href="#contact">Contact</a>
            </nav>
            <div class="nav-cta">
                <a class="btn btn-ghost btn-sm" href="{{ route('login') }}">Sign in</a>
            </div>
        </div>
    </header>

    <main id="top">
        <section class="hero">
            <div class="wrap hero-grid">
                <div>
                    <p class="eyebrow reveal">Srihari Energy · Field OS</p>
                    <h1 class="reveal reveal-d1">SEAS <span>commands the grid.</span></h1>
                    <p class="hero-lead reveal reveal-d2">
                        Ultra-precise DTR-to-consumer audits. Offline-ready field capture. Manager approvals. One black-and-red command surface.
                    </p>
                    <div class="hero-actions reveal reveal-d3">
                        <a class="btn btn-primary" href="{{ route('login') }}">Launch Super Admin</a>
                        <a class="btn btn-ghost" href="#platform">Explore platform</a>
                    </div>
                </div>

                <div class="dash reveal reveal-d2" aria-hidden="true">
                    <div class="dash-glow"></div>
                    <div class="dash-orb"></div>
                    <div class="dash-top">
                        <div class="dots"><span></span><span></span><span></span></div>
                        <span style="font-size:12px;color:var(--muted);font-family:Poppins,sans-serif;font-weight:600;letter-spacing:0.06em;">COMMAND CENTER</span>
                        <span style="width:48px"></span>
                    </div>
                    <div class="dash-body">
                        <div class="dash-kpis">
                            <div class="kpi"><b data-count="1284">0</b><span>Surveys</span></div>
                            <div class="kpi"><b data-count="96">0</b><span>Pending %</span></div>
                            <div class="kpi"><b data-count="42">0</b><span>Live FE</span></div>
                        </div>
                        <div class="dash-main">
                            <div class="panel">
                                <h4>14-day capture trend</h4>
                                <div class="chart">
                                    <i style="height:38%"></i><i style="height:52%"></i><i style="height:44%"></i>
                                    <i style="height:68%"></i><i style="height:55%"></i><i style="height:78%"></i>
                                    <i style="height:62%"></i><i style="height:88%"></i><i style="height:70%"></i>
                                    <i style="height:94%"></i>
                                </div>
                            </div>
                            <div class="panel">
                                <h4>Pipeline health</h4>
                                <div class="ring-wrap"><div class="ring"><strong>72%</strong></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="block stats" id="platform">
            <div class="wrap stats-grid reveal">
                <div class="stat"><b data-count="99">0</b><span>Offline sync reliability</span></div>
                <div class="stat"><b data-count="4">0</b><span>Role layers</span></div>
                <div class="stat"><b data-count="3">0</b><span>Survey stages</span></div>
                <div class="stat"><b data-count="1">0</b><span>Source of truth</span></div>
            </div>
        </section>

        <section class="block">
            <div class="wrap">
                <div class="sec-head reveal">
                    <p class="eyebrow">Capabilities</p>
                    <h2>Built for field velocity. Governed for audit truth.</h2>
                    <p>From transformer selection to consumer verification — every action is scoped, photographed, and reviewable.</p>
                </div>
                <div class="features">
                    <article class="card reveal">
                        <div class="card-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12h18M12 3v18M7 7l10 10M17 7 7 17"/></svg>
                        </div>
                        <h3>Hierarchy cascade</h3>
                        <p>Region → Circle → Division → Zone → Substation → Feeder → DTR. Zero ambiguity in the field.</p>
                    </article>
                    <article class="card reveal reveal-d1">
                        <div class="card-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12a8 8 0 0 1 8-8v0a8 8 0 0 1 8 8v0a8 8 0 0 1-8 8v0a8 8 0 0 1-8-8Z"/><path d="M12 8v4l3 2"/></svg>
                        </div>
                        <h3>Offline-first capture</h3>
                        <p>Drafts queue when the signal dies. Sync resumes the moment the grid reconnects.</p>
                    </article>
                    <article class="card reveal reveal-d2">
                        <div class="card-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 12l2 2 4-4"/><path d="M4 6h16v12H4z"/></svg>
                        </div>
                        <h3>Approval pipeline</h3>
                        <p>Managers review, approve, or reject with remarks. Field executives edit — never duplicate audits.</p>
                    </article>
                    <article class="card reveal">
                        <div class="card-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v18M5 8h14M5 16h14"/></svg>
                        </div>
                        <h3>Pole intelligence</h3>
                        <p>Source from DTR or previous pole. Expected houses tracked. Progress visible pole by pole.</p>
                    </article>
                    <article class="card reveal reveal-d1">
                        <div class="card-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10" cy="10" r="7"/><path d="M10 6v4l3 2"/></svg>
                        </div>
                        <h3>Consumer identify</h3>
                        <p>Scan MSN, enter serial, or search IVRS. Master/WFM data appears instantly when found.</p>
                    </article>
                    <article class="card reveal reveal-d2">
                        <div class="card-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 15l4-8 4 4 4-8 4 12"/></svg>
                        </div>
                        <h3>Exception capture</h3>
                        <p>Not accessible or permanently disconnected — reason, mandatory photo, audit trail intact.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="block" id="workflow" style="background:#080808;border-block:1px solid var(--border)">
            <div class="wrap">
                <div class="sec-head reveal">
                    <p class="eyebrow">How it works</p>
                    <h2>Four stages. Zero chaos.</h2>
                    <p>A cinematic field process designed for speed without sacrificing evidence.</p>
                </div>
                <div class="process reveal">
                    <div class="step">
                        <h3>Audit the DTR</h3>
                        <p>GPS, condition, meter status, photos — submitted for manager review.</p>
                    </div>
                    <div class="step">
                        <h3>Map the poles</h3>
                        <p>Declare source and expected consumers. Track surveyed vs pending.</p>
                    </div>
                    <div class="step">
                        <h3>Identify consumers</h3>
                        <p>MSN or IVRS. Verify master data or register a new consumer cleanly.</p>
                    </div>
                    <div class="step">
                        <h3>Close exceptions</h3>
                        <p>Locked premises, missing meters, PDC — captured with proof.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="block" id="intelligence">
            <div class="wrap split">
                <div class="reveal">
                    <p class="eyebrow">Command surface</p>
                    <h2 style="margin-top:14px;font-size:clamp(2rem,4vw,2.8rem);font-weight:700;">One view. Full pulse.</h2>
                    <p class="muted" style="margin-top:14px;font-size:1.05rem;max-width:42ch;">
                        Super Admin sees inventory, pipeline, trends, and team roles without leaving the console. Managers live in Flutter. Executives capture in the field.
                    </p>
                    <div class="hero-actions" style="margin-top:28px">
                        <a class="btn btn-primary" href="{{ route('login') }}">Enter dashboard</a>
                    </div>
                </div>
                <div class="glass reveal reveal-d2">
                    <div class="row"><span class="gray">Pending approvals</span><span class="pill">LIVE</span></div>
                    <div class="row"><span class="gray">DTR audits locked</span><span class="pill ok">ENFORCED</span></div>
                    <div class="row"><span class="gray">Consumer exceptions</span><span class="pill">PHOTO REQUIRED</span></div>
                    <div class="row"><span class="gray">Offline queue</span><span class="pill ok">AUTO SYNC</span></div>
                    <div class="row"><span class="gray">Role scopes</span><span class="pill ok">RBAC</span></div>
                </div>
            </div>
        </section>

        <section class="block" id="contact">
            <div class="wrap">
                <div class="cta reveal">
                    <p class="eyebrow">Ready</p>
                    <h2>Operate the network like a product.</h2>
                    <p>Sign in to the Super Admin console, or hand Flutter to your field teams. Same truth. Different surfaces.</p>
                    <div class="hero-actions">
                        <a class="btn btn-primary" href="{{ route('login') }}">Open SEAS Console</a>
                        <a class="btn btn-ghost" href="mailto:ops@sriharienergy.test">Talk to ops</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="wrap">
            <div class="foot">
                <a class="logo" href="#top">
                    <span class="logo-mark"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" width="18" height="18"><path d="M13 2 4 14h7l-1 8 10-14h-7l0-6z"/></svg></span>
                    SEAS
                </a>
                <nav>
                    <a href="#platform">Platform</a>
                    <a href="#contact">Contact</a>
                    <a href="{{ route('login') }}">Sign in</a>
                </nav>
            </div>
            <p class="copy">© {{ date('Y') }} Srihari Energy · SEAS Field Intelligence. All rights reserved.</p>
        </div>
    </footer>

    <script>
        const nav = document.getElementById('nav');

        window.addEventListener('scroll', () => {
            nav.classList.toggle('scrolled', window.scrollY > 12);
        }, { passive: true });

        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        document.querySelectorAll('.reveal').forEach(el => io.observe(el));

        function animateCount(el) {
            const target = parseInt(el.getAttribute('data-count') || '0', 10);
            const start = performance.now();
            const dur = 1100;
            const tick = (now) => {
                const p = Math.min(1, (now - start) / dur);
                const eased = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(target * eased);
                if (p < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        }
        const cio = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    animateCount(e.target);
                    cio.unobserve(e.target);
                }
            });
        }, { threshold: 0.4 });
        document.querySelectorAll('[data-count]').forEach(el => cio.observe(el));
    </script>
</body>
</html>
