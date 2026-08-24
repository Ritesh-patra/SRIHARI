<x-app-layout>
    <div class="mx-auto w-full space-y-5 sm:space-y-6">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('success') }}</div>
        @endif

        {{-- Hero banner (Franklin-style) --}}
        <section class="al-hero relative p-6 sm:p-7 lg:p-8">
            <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <span class="al-chip bg-black/30 text-white ring-1 ring-white/20">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                        Overview
                    </span>
                    <h1 class="al-display mt-3 text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Dashboard</h1>
                    <p class="mt-2 text-sm text-white/75 sm:text-[15px]">
                        Welcome, {{ auth()->user()->name }} — manage users, hierarchy, masters, and survey pipeline.
                    </p>
                    <p class="mt-3 text-xs font-medium text-white/50">{{ now()->format('l, d M Y') }}</p>
                </div>
                <div class="flex flex-wrap gap-2.5">
                    <a href="{{ route('admin.users') }}" class="al-btn al-btn-light px-4 py-2.5 text-sm">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                        Manage Users
                    </a>
                    <a href="{{ route('reports.index') }}" class="al-btn al-btn-glass px-4 py-2.5 text-sm">
                        Open Reports
                    </a>
                </div>
            </div>
        </section>

        {{-- Survey progress (clickable) --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach($progressTiles as $tile)
                @php
                    $pct = $tile['total'] > 0 ? min(100, round(($tile['done'] / $tile['total']) * 100)) : 0;
                @endphp
                <a href="{{ $tile['href'] }}" class="al-panel block px-5 py-5 transition hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-volt/40">
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-muted">{{ $tile['label'] }} Survey</div>
                        <span class="text-[10px] font-bold uppercase tracking-wide text-volt">Open →</span>
                    </div>
                    <div class="mt-3 flex items-end justify-between gap-3">
                        <div>
                            <div class="al-display text-3xl font-extrabold text-theme sm:text-4xl" data-count="{{ $tile['done'] }}">0</div>
                            <div class="mt-1 text-xs font-semibold text-emerald-400">Surveyed</div>
                        </div>
                        <div class="text-right">
                            <div class="al-display text-3xl font-extrabold text-white/90 sm:text-4xl" data-count="{{ $tile['remaining'] }}">0</div>
                            <div class="mt-1 text-xs font-semibold text-amber-300">Pending</div>
                        </div>
                    </div>
                    <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-white/10">
                        <div class="h-full rounded-full bg-volt" style="width: {{ $pct }}%"></div>
                    </div>
                    <div class="mt-2 text-[11px] text-muted">{{ $tile['done'] }} of {{ $tile['total'] }} · {{ $pct }}%</div>
                </a>
            @endforeach
        </section>

        {{-- Pipeline --}}
        <section class="al-panel overflow-hidden">
            <div class="grid grid-cols-2 sm:grid-cols-4">
                @foreach([
                    ['Pending', $stats['pending'], 'text-amber-600 dark:text-amber-400'],
                    ['Approved', $stats['approved'], 'text-emerald-600 dark:text-emerald-400'],
                    ['Rejected', $stats['rejected'], 'text-muted'],
                    ['Completed', $stats['completed'], 'text-theme'],
                ] as $i => [$label, $val, $tone])
                    <div class="px-4 py-5 text-center sm:px-6 sm:py-6 {{ $i > 0 ? 'border-l' : '' }} {{ $i > 1 ? 'border-t sm:border-t-0' : '' }} {{ $i === 1 ? 'border-t-0 sm:border-t-0' : '' }}" style="border-color: var(--al-border)">
                        <div class="al-display text-3xl font-extrabold text-theme sm:text-4xl" data-count="{{ $val }}">0</div>
                        <div class="mt-1 text-[11px] font-bold uppercase tracking-[0.12em] {{ $tone }}">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Key metrics --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            @foreach([
                ['Users', $stats['users'], $stats['active_users'].' active', route('admin.users')],
                ['Field Executives', $stats['field_executives'], $stats['managers'].' managers', route('admin.users')],
                ['Feeders', $stats['feeders'], 'Master feeders', route('admin.masters')],
                ['DTRs', $stats['dtrs'], 'Distribution transformers', route('admin.masters.dtrs.index')],
                ['Consumers', $stats['consumers'], $stats['consumer_surveys'].' surveyed', route('admin.masters.consumers.index')],
            ] as [$label, $val, $sub, $href])
                <a href="{{ $href }}" class="al-panel block px-5 py-5 transition hover:-translate-y-0.5">
                    <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-muted">{{ $label }}</div>
                    <div class="al-display mt-2 text-3xl font-extrabold text-theme" data-count="{{ $val }}">0</div>
                    <div class="mt-1 text-xs text-muted">{{ $sub }}</div>
                </a>
            @endforeach
        </section>

        {{-- Charts --}}
        <section class="grid gap-4 xl:grid-cols-12">
            <div class="al-panel p-5 sm:p-6 xl:col-span-7">
                <div class="mb-4">
                    <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-volt">Analytics</div>
                    <h3 class="al-display text-lg font-bold text-theme">Surveys · last 14 days</h3>
                </div>
                <div class="relative h-56 sm:h-64 lg:h-72">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <div class="al-panel flex flex-col p-5 sm:p-6 xl:col-span-5">
                <div class="mb-3">
                    <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-volt">Pipeline</div>
                    <h3 class="al-display text-lg font-bold text-theme">Status mix</h3>
                </div>
                <div class="relative mx-auto h-40 w-40 sm:h-44 sm:w-44">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="mt-5 grid grid-cols-5 gap-2 text-center">
                    @foreach([
                        ['Draft', $stats['draft']],
                        ['Pend.', $stats['pending']],
                        ['Appr.', $stats['approved']],
                        ['Rej.', $stats['rejected']],
                        ['Done', $stats['completed']],
                    ] as [$l, $v])
                        <div class="py-1">
                            <div class="al-display text-sm font-bold text-theme" data-count="{{ $v }}">0</div>
                            <div class="text-[9px] font-bold uppercase tracking-wide text-muted">{{ $l }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Team / Live / Jumps --}}
        <section class="grid gap-4 lg:grid-cols-12">
            <div class="al-panel p-5 sm:p-6 lg:col-span-4">
                <div class="mb-4">
                    <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-volt">Team</div>
                    <h3 class="al-display text-lg font-bold text-theme">Users by role</h3>
                </div>
                <div class="relative h-52 sm:h-56">
                    <canvas id="roleChart"></canvas>
                </div>
            </div>

            <div class="al-panel overflow-hidden lg:col-span-5">
                <div class="flex items-center justify-between border-b px-5 py-4" style="border-color: var(--al-border)">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-volt">Live</div>
                        <h3 class="al-display text-lg font-bold text-theme">Latest field surveys</h3>
                    </div>
                    <a href="{{ route('reports.index') }}" class="text-xs font-bold text-volt hover:underline">View all</a>
                </div>
                <div class="max-h-64 divide-y overflow-y-auto sm:max-h-72" style="border-color: var(--al-border)">
                    @forelse($recentSurveys as $item)
                        <div class="flex items-center justify-between gap-3 px-5 py-3.5" style="border-color: var(--al-border)">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold text-theme">{{ $item->dtr_name }}</div>
                                <div class="text-[11px] text-muted">{{ $item->surveyor?->name }} · {{ $item->surveyed_at?->format('d M Y H:i') }}</div>
                            </div>
                            <span class="shrink-0 text-[11px] font-semibold text-muted">{{ $item->displayStatusLabel() }}</span>
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center text-sm text-muted">No field data yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="al-panel p-5 sm:p-6 lg:col-span-3">
                <div class="mb-4">
                    <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-volt">System</div>
                    <h3 class="al-display text-lg font-bold text-theme">Health & jumps</h3>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between border-b pb-3" style="border-color: var(--al-border)">
                        <span class="text-xs font-semibold text-muted">API</span>
                        <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">Online</span>
                    </div>
                    <div class="flex items-center justify-between border-b pb-3" style="border-color: var(--al-border)">
                        <span class="text-xs font-semibold text-muted">Pending reviews</span>
                        <span class="text-[11px] font-bold text-amber-600 dark:text-amber-400">{{ $stats['pending'] }}</span>
                    </div>
                    @foreach([
                        [route('admin.masters'), 'Masters', $stats['consumers'].' consumers'],
                        [route('admin.hierarchy'), 'Hierarchy', 'Region → DTR'],
                        [route('admin.activity'), 'Audit Logs', 'Activity stream'],
                    ] as [$href, $title, $sub])
                        <a href="{{ $href }}" class="block rounded-xl py-2 transition hover:opacity-80">
                            <div class="text-sm font-bold text-theme">{{ $title }}</div>
                            <div class="text-[11px] text-muted">{{ $sub }}</div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Recent users table --}}
        <section class="al-panel overflow-hidden">
            <div class="flex items-center justify-between border-b px-5 py-4" style="border-color: var(--al-border)">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-volt">Directory</div>
                    <h3 class="al-display text-lg font-bold text-theme">Recent users</h3>
                </div>
                <a href="{{ route('admin.users') }}" class="text-xs font-bold text-volt hover:underline">Manage</a>
            </div>
            <div class="overflow-x-auto">
                <table class="al-table min-w-full">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentUsers as $u)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-seas-950 text-xs font-bold text-white">{{ strtoupper(substr($u->name,0,1)) }}</span>
                                        <div>
                                            <div class="font-bold text-theme">{{ $u->name }}</div>
                                            <div class="text-xs text-muted">{{ $u->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="al-chip bg-volt-soft text-volt-deep dark:bg-volt/20 dark:text-red-300">{{ $u->roleLabel() }}</span></td>
                                <td>
                                    <span class="al-chip {{ $u->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-seas-100 text-seas-500 dark:bg-white/5 dark:text-zinc-400' }}">
                                        {{ $u->is_active ? 'Active' : 'Off' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            document.querySelectorAll('[data-count]').forEach((el) => {
                const target = parseInt(el.getAttribute('data-count') || '0', 10);
                const start = performance.now();
                const dur = 900;
                function tick(now) {
                    const p = Math.min(1, (now - start) / dur);
                    const eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.round(target * eased);
                    if (p < 1) requestAnimationFrame(tick);
                }
                requestAnimationFrame(tick);
            });

            const red = '#E10600';
            const ink = document.documentElement.classList.contains('dark') ? '#F5F5F5' : '#0A0A0A';
            const muted = '#A3A3A3';
            Chart.defaults.color = muted;
            Chart.defaults.borderColor = 'rgba(120,120,120,0.12)';
            Chart.defaults.font.family = 'Inter, system-ui, sans-serif';

            const trend = @json($trendChart);
            const status = @json($statusChart);
            const roles = @json($roleChart);

            new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: trend.labels,
                    datasets: [{
                        data: trend.values,
                        borderColor: red,
                        backgroundColor: (ctx) => {
                            const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 220);
                            g.addColorStop(0, 'rgba(225,6,0,0.18)');
                            g.addColorStop(1, 'rgba(225,6,0,0)');
                            return g;
                        },
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        pointBackgroundColor: red,
                        borderWidth: 2.5,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: muted, maxRotation: 0, font: { size: 10 } }, grid: { display: false } },
                        y: { beginAtZero: true, ticks: { color: muted, precision: 0 }, grid: { color: 'rgba(120,120,120,0.1)' } }
                    }
                }
            });

            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: status.labels,
                    datasets: [{
                        data: status.values,
                        backgroundColor: ['#D4D4D4', '#F59E0B', '#10B981', red, '#0A0A0A'],
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: { legend: { display: false } }
                }
            });

            new Chart(document.getElementById('roleChart'), {
                type: 'bar',
                data: {
                    labels: roles.labels,
                    datasets: [{
                        data: roles.values,
                        backgroundColor: [ink, '#737373', red, '#FF4D4D'],
                        borderRadius: 8,
                        barThickness: 18,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0, color: muted }, grid: { color: 'rgba(120,120,120,0.1)' } },
                        y: {
                            ticks: {
                                color: ink,
                                font: { weight: '600', size: 11 },
                                autoSkip: false,
                                callback: function (val) {
                                    const label = this.getLabelForValue(val);
                                    return label.length > 14 ? label.slice(0, 12) + '…' : label;
                                }
                            },
                            grid: { display: false }
                        }
                    }
                }
            });
        })();
    </script>
    @endpush
</x-app-layout>
