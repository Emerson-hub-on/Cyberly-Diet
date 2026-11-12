{{--
    Navegação por data: ← dia anterior · data (toque abre o calendário) · próximo dia →
    Usa a função Alpine link(data) do componente pai, se houver (o painel mantém a aba aberta);
    sem ela, os links viram ?data=AAAA-MM-DD na página atual.
--}}
@props(['date', 'ownLink' => false])
@php
    $prev = $date->subDay()->toDateString();
    $next = $date->addDay()->toDateString();
    $dateLabel = match (true) {
        $date->isToday() => 'Hoje',
        $date->isYesterday() => 'Ontem',
        $date->isTomorrow() => 'Amanhã',
        default => ucfirst($date->translatedFormat('l')),
    } . ' - ' . $date->format('d/m/Y');
@endphp
<div @if ($ownLink) x-data="{ link: (d) => '?data=' + d }" @endif {{ $attributes->merge(['class' => 'flex items-center justify-between px-1']) }}>
    <a href="?data={{ $prev }}" :href="link('{{ $prev }}')" class="rounded-full p-1.5 text-navy-600 hover:bg-white" aria-label="Dia anterior">
        <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
    </a>
    {{-- Data + calendário: o input de data invisível cobre o bloco e abre o seletor nativo --}}
    <label class="relative flex cursor-pointer items-center gap-2 rounded-xl px-3 py-1.5 transition hover:bg-white" title="Escolher data no calendário">
        <span class="font-medium text-slate-700">{{ $dateLabel }}</span>
        <svg class="size-5 text-navy-600" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z"/></svg>
        <input type="date" value="{{ $date->toDateString() }}" max="{{ now()->addYear()->toDateString() }}"
               aria-label="Escolher data"
               class="date-overlay absolute inset-0 h-full w-full cursor-pointer opacity-0"
               @click="try { $el.showPicker() } catch (e) {}"
               @change="if ($event.target.value) window.location = link($event.target.value)">
    </label>
    <a href="?data={{ $next }}" :href="link('{{ $next }}')" class="rounded-full p-1.5 text-navy-600 hover:bg-white" aria-label="Próximo dia">
        <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
    </a>
</div>
