@props(['meal', 'compact' => false])
@php
    $g = fn ($v) => rtrim(rtrim(number_format((float) ($v ?? 0), 1, ',', '.'), '0'), ',');
    $fiberGoal = config('diet.fiber_goal_g');
    $fiberPct = $fiberGoal > 0 ? (int) round($meal->fiber_g / $fiberGoal * 100) : 0;
    $confidence = ['baixa' => 'baixa', 'media' => 'média', 'alta' => 'alta'][$meal->confidence] ?? null;
@endphp

<div x-data="{ open: false }" {{ $attributes }}>
    {{-- Linha resumida (toque para abrir os detalhes) --}}
    <button type="button" @click="open = true" class="group flex w-full items-center gap-3 rounded-xl text-left transition hover:bg-ice-50 active:scale-[.99]">
        @if ($meal->photo_path)
            <img src="{{ route('meals.photo', $meal) }}" alt="" loading="lazy" class="{{ $compact ? 'size-14' : 'size-16' }} shrink-0 rounded-xl object-cover">
        @endif
        <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-navy-600">
                {{ $meal->type_label }} <span class="font-normal text-slate-400">{{ $meal->eaten_at->format('H:i') }}</span>
            </p>
            <p class="truncate {{ $compact ? 'text-sm' : '' }} font-medium text-slate-800">{{ $meal->title }}</p>
            <p class="{{ $compact ? 'text-xs' : 'text-sm' }} text-slate-500">
                <span class="font-semibold text-slate-700">{{ $meal->calories }} Kcal</span>
                · {{ $g($meal->carbs_g) }} g Carb. · {{ $g($meal->protein_g) }} g Prot. · {{ $g($meal->fat_g) }} g Gord.
            </p>
        </div>
        <svg class="size-5 shrink-0 text-slate-300 transition group-hover:text-navy-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
    </button>

    {{-- Modal com os detalhes --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" @keydown.escape.window="open = false">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-navy-950/60 backdrop-blur-sm" @click="open = false"></div>

            <div x-show="open"
                 x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0"
                 x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0"
                 class="relative max-h-[92dvh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-white p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-2xl sm:rounded-3xl">

                <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-ice-200 sm:hidden"></div>

                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-navy-600">
                            {{ $meal->type_label }} · {{ $meal->eaten_at->format('d/m/Y H:i') }}
                        </p>
                        <h3 class="text-lg font-bold text-navy-800">{{ $meal->title }}</h3>
                    </div>
                    <button type="button" class="shrink-0 rounded-full p-1.5 text-slate-400 hover:bg-ice-100" @click="open = false" aria-label="Fechar">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                @if ($meal->photo_path)
                    <img x-bind:src="open ? '{{ route('meals.photo', $meal) }}' : ''" alt="Foto do prato" class="mb-4 h-48 w-full rounded-2xl object-cover">
                @endif

                {{-- Totais --}}
                <div class="flex items-end justify-between">
                    <p class="text-4xl font-light text-navy-800">{{ $meal->calories }} <span class="text-xl">Kcal</span></p>
                    <p class="text-sm text-slate-500">{{ $g($meal->goal_pct) }}% da meta diária</p>
                </div>
                {{-- Indicadores: deslize para o lado para ver as fibras --}}
                <div class="no-scrollbar mt-3 flex snap-x snap-mandatory gap-2 overflow-x-auto text-center text-xs">
                    <div class="w-[calc((100%-1.5rem)/4.25)] shrink-0 snap-start rounded-xl bg-cyan-50 p-2"><p class="text-base font-bold text-carbs">{{ $g($meal->carbs_g) }}g</p>Carbos<p class="text-slate-400">{{ $g($meal->carbs_pct) }}%</p></div>
                    <div class="w-[calc((100%-1.5rem)/4.25)] shrink-0 snap-start rounded-xl bg-blue-50 p-2"><p class="text-base font-bold text-protein">{{ $g($meal->protein_g) }}g</p>Proteínas<p class="text-slate-400">{{ $g($meal->protein_pct) }}%</p></div>
                    <div class="w-[calc((100%-1.5rem)/4.25)] shrink-0 snap-start rounded-xl bg-indigo-50 p-2"><p class="text-base font-bold text-fat">{{ $g($meal->fat_g) }}g</p>Gorduras<p class="text-slate-400">{{ $g($meal->fat_pct) }}%</p></div>
                    <div class="w-[calc((100%-1.5rem)/4.25)] shrink-0 snap-start rounded-xl bg-slate-50 p-2"><p class="text-base font-bold text-slate-500">{{ $g($meal->sugar_g) }}g</p>Açúcar</div>
                    <div class="w-[calc((100%-1.5rem)/4.25)] shrink-0 snap-start rounded-xl bg-emerald-50 p-2"><p class="text-base font-bold text-emerald-600">{{ $g($meal->fiber_g) }}g</p>Fibras<p class="text-slate-400">{{ $fiberPct }}% de {{ $fiberGoal }}g</p></div>
                </div>
                <p class="mt-1 flex justify-between gap-2 text-[11px] text-slate-400"><span>% = participação nas calorias da refeição</span><span class="shrink-0">Deslize → fibras</span></p>

                {{-- Detalhamento por alimento --}}
                @if (! empty($meal->items))
                    <h4 class="mb-2 mt-4 text-sm font-bold text-slate-700">Alimentos identificados</h4>
                    <x-meal-items :items="$meal->items" />
                @endif

                @if ($meal->notes)
                    <p class="mt-3 text-xs text-slate-500"><b>Sua observação:</b> {{ $meal->notes }}</p>
                @endif
                @if ($meal->ai_notes)
                    <p class="mt-2 text-xs text-slate-500">💡 {{ $meal->ai_notes }}</p>
                @endif
                <p class="mt-2 text-[11px] text-slate-400">
                    Estimativa feita por IA a partir da foto{{ $confidence ? " (confiança: {$confidence})" : '' }}. Os valores são aproximados.
                </p>

                <div class="mt-4 flex gap-2">
                    <form method="POST" action="{{ route('meals.destroy', $meal) }}" onsubmit="return confirm('Remover esta refeição?')" class="flex-1">
                        @csrf @method('DELETE')
                        <button class="w-full rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50">Remover</button>
                    </form>
                    <button type="button" class="btn-primary flex-1" @click="open = false">Fechar</button>
                </div>
            </div>
        </div>
    </template>
</div>
