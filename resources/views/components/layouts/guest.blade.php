<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#16365f">
    @include('partials.pwa-head')
    <title>{{ $title ?? 'Entrar' }} · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-dvh items-center justify-center bg-gradient-to-b from-navy-800 via-navy-700 to-navy-500 p-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center text-white">
            <div class="mx-auto mb-3 flex size-14 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20">
                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
            </div>
            <h1 class="text-2xl font-bold tracking-wide">{{ config('app.name') }}</h1>
            <p class="text-sm text-sky-100/80">Sua dieta, prato a prato.</p>
        </div>
        <div class="rounded-3xl bg-white p-6 shadow-2xl">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
