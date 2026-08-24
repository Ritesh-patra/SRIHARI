<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0A0A0A">

        <title>{{ config('app.name', 'SEAS') }} · Sign in</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-seas-900 antialiased bg-white">
        <div class="relative flex min-h-screen overflow-hidden">
            {{-- Black brand panel --}}
            <div class="relative hidden w-[46%] flex-col justify-between bg-seas-950 p-10 text-white lg:flex xl:w-[48%] xl:p-14 2xl:p-16">
                <div class="pointer-events-none absolute inset-0 opacity-[0.07]" style="background-image:linear-gradient(rgba(255,255,255,.2) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.2) 1px,transparent 1px);background-size:36px 36px"></div>
                <div class="pointer-events-none absolute -right-16 top-20 h-72 w-72 rounded-full bg-volt/40 blur-3xl"></div>
                <div class="relative z-10">
                    <div class="flex items-center gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-volt text-white shadow-glow">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 4 14h7l-1 8 10-14h-7l1-6Z"/></svg>
                        </span>
                        <div>
                            <div class="font-display text-2xl font-extrabold tracking-tight xl:text-3xl">SEAS</div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.22em] text-white/45">Smart Energy Audit</div>
                        </div>
                    </div>
                </div>
                <div class="relative z-10 max-w-md xl:max-w-lg">
                    <h1 class="font-display text-4xl font-extrabold leading-tight tracking-tight xl:text-5xl 2xl:text-6xl">
                        Audit the grid.<br>
                        <span class="text-volt">One DTR at a time.</span>
                    </h1>
                    <p class="mt-4 text-base text-white/55 xl:text-lg">
                        From feeder to consumer — capture, approve, and close field surveys.
                    </p>
                </div>
                <p class="relative z-10 text-xs text-white/35">MPMKVVCL · Field Operations</p>
            </div>

            {{-- White form panel --}}
            <div class="relative flex flex-1 flex-col items-center justify-center bg-white px-5 py-12 sm:px-8 lg:px-12 xl:px-16">
                <div class="relative z-10 w-full max-w-md xl:max-w-lg animate-fade-up">
                    <div class="mb-8 text-center lg:hidden">
                        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-seas-950 text-volt shadow-seas-lg">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 4 14h7l-1 8 10-14h-7l1-6Z"/></svg>
                        </div>
                        <h1 class="font-display text-3xl font-extrabold text-seas-900">SEAS</h1>
                        <p class="mt-1 text-xs font-bold uppercase tracking-[0.18em] text-seas-400">Smart Energy Audit</p>
                    </div>

                    <div class="mb-6">
                        <h2 class="font-display text-2xl font-extrabold text-seas-900">Sign in</h2>
                        <p class="mt-1 text-sm text-seas-400">Use your field / manager credentials</p>
                    </div>

                    <div class="seas-card-pad shadow-seas-lg">
                        {{ $slot }}
                    </div>

                    <p class="mt-6 text-center text-xs text-seas-400">Super Admin web · super@seas.test / password<br>Manager / Field Executive → Flutter app</p>
                </div>
            </div>
        </div>
    </body>
</html>
