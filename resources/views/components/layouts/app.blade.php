<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#16365f">
    @include('partials.pwa-head')
    <title>{{ $title ?? 'Evolução' }} · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh">
    {{-- Cabeçalho --}}
    <header class="sticky top-0 z-30 bg-navy-700 pt-[env(safe-area-inset-top)] text-white shadow-md">
        <div class="mx-auto flex h-14 max-w-md items-center justify-between px-4">
            <a href="{{ route('profile') }}#eventos" class="rounded-lg p-1.5 hover:bg-white/10" aria-label="Eventos">
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
            </a>
            <h1 class="text-lg font-bold uppercase tracking-[0.2em]">{{ $title ?? 'Evolução' }}</h1>
            <a href="{{ route('profile') }}" class="rounded-lg p-1.5 hover:bg-white/10" aria-label="Perfil">
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/></svg>
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-md px-4 pb-32 pt-4">
        @if (session('status'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
                 class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-medium text-navy-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        {{ $slot }}
    </main>

    {{-- Navegação inferior --}}
    @php($current = request()->route()?->getName())
    {{-- x-data é necessário para o Alpine ativar o @click do botão "+" --}}
    {{-- Menu inferior flutuante com efeito vidro (Liquid Glass) --}}
    <nav x-data class="pointer-events-none fixed inset-x-0 bottom-0 z-30 px-3 pb-[max(env(safe-area-inset-bottom),0.75rem)]">
        <div class="glass-bar pointer-events-auto mx-auto grid h-16 max-w-md grid-cols-5 items-center px-1.5 text-[11px] font-medium">
            <a href="{{ route('home') }}" @class(['mx-0.5 flex flex-col items-center gap-0.5 py-1.5 transition', 'glass-pill text-navy-700 font-bold' => $current === 'home' && request('tab', 'calorias') !== 'agua', 'text-slate-400' => ! ($current === 'home' && request('tab', 'calorias') !== 'agua')])>
                <svg class="size-6" fill="currentColor" viewBox="0 0 24 24"><path d="M11.47 3.84a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 1 0 1.06-1.06l-8.69-8.69a2.25 2.25 0 0 0-3.18 0l-8.69 8.69a.75.75 0 0 0 1.06 1.06l8.69-8.69Z"/><path d="m12 5.43 8.16 8.16.09.08v6.2c0 1.04-.84 1.88-1.88 1.88H15a.75.75 0 0 1-.75-.75v-4.5a.75.75 0 0 0-.75-.75h-3a.75.75 0 0 0-.75.75V21a.75.75 0 0 1-.75.75H5.63a1.88 1.88 0 0 1-1.88-1.88v-6.2l.09-.08L12 5.43Z"/></svg>
                Início
            </a>
            <a href="{{ route('meals.index') }}" @class(['mx-0.5 flex flex-col items-center gap-0.5 py-1.5 transition', 'glass-pill text-navy-700 font-bold' => $current === 'meals.index', 'text-slate-400' => $current !== 'meals.index'])>
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.871c1.355 0 2.697.056 4.024.166C17.155 8.51 18 9.473 18 10.608v2.513M15 8.25v-1.5m-6 1.5v-1.5m12 9.75-1.5.75a3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 0-3 0 3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 0-3 0 3.354 3.354 0 0 1-3 0L3 16.5m15-3.379a48.474 48.474 0 0 0-6-.371c-2.032 0-4.034.126-6 .371m12 0c.39.049.777.102 1.163.16 1.07.16 1.837 1.094 1.837 2.175v5.169c0 .621-.504 1.125-1.125 1.125H4.125A1.125 1.125 0 0 1 3 20.625v-5.17c0-1.08.768-2.014 1.837-2.174A47.78 47.78 0 0 1 6 13.12M12.265 3.11a.375.375 0 1 1-.53 0L12 2.845l.265.265Z"/></svg>
                Refeições
            </a>
            <div class="flex justify-center">
                <button type="button" @click="$store.mealModal.show()"
                        class="glass-fab -mt-8 flex size-14 items-center justify-center rounded-full text-white transition hover:brightness-110 active:scale-95"
                        aria-label="Adicionar refeição">
                    <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </button>
            </div>
            <a href="{{ route('home', ['tab' => 'agua']) }}" @class(['mx-0.5 flex flex-col items-center gap-0.5 py-1.5 transition', 'glass-pill text-navy-700 font-bold' => $current === 'home' && request('tab') === 'agua', 'text-slate-400' => ! ($current === 'home' && request('tab') === 'agua')])>
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5s-6 6.6-6 11a6 6 0 0 0 12 0c0-4.4-6-11-6-11Z"/></svg>
                Água
            </a>
            <a href="{{ route('profile') }}" @class(['mx-0.5 flex flex-col items-center gap-0.5 py-1.5 transition', 'glass-pill text-navy-700 font-bold' => $current === 'profile', 'text-slate-400' => $current !== 'profile'])>
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                Perfil
            </a>
        </div>
    </nav>

    @include('partials.meal-modal')
</body>
</html>
