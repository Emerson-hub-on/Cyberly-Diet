{{-- Modal "Nutrientes do dia": aberto ao tocar num anel. Usa $meals, $totals, $nutrition, $sugarLimit, $fiberGoal, $grams. --}}
@php
    // chave => [rótulo, coluna, cor, alvo, é limite?]
    $nutrients = [
        'protein' => ['Proteínas', 'protein_g', '#3b82f6', $nutrition['values']['protein'], false],
        'carbs' => ['Carboidratos', 'carbs_g', '#06b6d4', $nutrition['values']['carbs'], false],
        'fat' => ['Gorduras', 'fat_g', '#6366f1', $nutrition['values']['fat'], false],
        'sugar' => ['Açúcar', 'sugar_g', '#94a3b8', $sugarLimit, true],
        'fiber' => ['Fibras', 'fiber_g', '#10b981', $fiberGoal, false],
    ];
@endphp
<template x-teleport="body">
    <div x-show="nutrient" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center"
         @click.self="nutrient = null" @keydown.escape.window="nutrient = null" role="dialog" aria-modal="true">
        <div x-show="nutrient" x-transition class="max-h-[85dvh] w-full max-w-md overflow-y-auto overflow-x-hidden rounded-3xl bg-white p-5 shadow-xl">
            @foreach ($nutrients as $key => [$label, $col, $color, $target, $isLimit])
                @php
                    $eaten = (float) $totals[$col];
                    $diff = $target - $eaten;
                    $pct = $target > 0 ? (int) round($eaten / $target * 100) : 0;
                    $foods = config("nutrients.$key");
                @endphp
                <div x-show="nutrient === '{{ $key }}'" data-nutrient="{{ $key }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="size-3 shrink-0 rounded-full" style="background: {{ $color }}"></span>
                            <h3 class="text-lg font-bold text-slate-800">{{ $label }} do dia</h3>
                        </div>
                        <button type="button" @click="nutrient = null" class="rounded-full p-1 text-slate-400 hover:bg-ice-100 hover:text-slate-600" aria-label="Fechar">
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Barra de progresso --}}
                    <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-ice-100">
                        <div class="h-full rounded-full" style="width: {{ min($pct, 100) }}%; background: {{ $isLimit && $pct > 100 ? '#f43f5e' : $color }}"></div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ $pct }}% {{ $isLimit ? 'do limite' : 'da meta' }}</p>

                    {{-- Tabela: o que já foi consumido e quanto falta --}}
                    <table class="mt-4 w-full table-fixed text-sm">
                        <colgroup><col><col class="w-24"></colgroup>
                        <thead>
                            <tr class="border-b border-ice-100 text-left text-xs uppercase tracking-wide text-slate-400">
                                <th class="pb-2 font-semibold">Refeição</th>
                                <th class="pb-2 text-right font-semibold">Gramas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($meals as $meal)
                                <tr class="border-b border-ice-50">
                                    <td class="py-2 pr-2">
                                        <span class="font-medium text-slate-700">{{ $meal->type_label }}</span>
                                        <span class="text-xs text-slate-400">{{ $meal->eaten_at->format('H:i') }}</span>
                                        @if ($meal->title)
                                            <span class="line-clamp-2 block break-words text-xs text-slate-400">{{ $meal->title }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-right font-medium text-slate-700">{{ $meal->{$col} !== null ? $grams($meal->{$col}).' g' : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="py-3 text-center text-xs text-slate-400">Nenhuma refeição registrada neste dia.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="pt-3 font-semibold text-slate-700">Consumido</td>
                                <td class="pt-3 text-right font-bold text-slate-800">{{ $grams($eaten) }} g</td>
                            </tr>
                            <tr>
                                <td class="py-1 text-slate-500">{{ $isLimit ? 'Limite do dia' : 'Meta do dia' }}</td>
                                <td class="py-1 text-right text-slate-500">{{ $target }} g</td>
                            </tr>
                            <tr>
                                @if ($isLimit)
                                    @if ($diff >= 0)
                                        <td class="font-semibold text-emerald-600">Ainda dentro do limite</td>
                                        <td class="text-right font-bold text-emerald-600">{{ $grams($diff) }} g</td>
                                    @else
                                        <td class="font-semibold text-rose-500">Passou do limite</td>
                                        <td class="text-right font-bold text-rose-500">{{ $grams(-$diff) }} g</td>
                                    @endif
                                @elseif ($diff > 0)
                                    <td class="font-semibold text-navy-700">Falta</td>
                                    <td class="text-right font-bold text-navy-700">{{ $grams($diff) }} g</td>
                                @else
                                    <td class="font-semibold text-emerald-600">Meta atingida ✓</td>
                                    <td class="text-right font-bold text-emerald-600">{{ $diff < 0 ? '+'.$grams(-$diff).' g' : '' }}</td>
                                @endif
                            </tr>
                        </tfoot>
                    </table>

                    {{-- Alimentos ricos neste nutriente --}}
                    <h4 class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $foods['title'] }}</h4>
                    <ul class="mt-2 divide-y divide-ice-50 rounded-xl bg-ice-50 px-3">
                        @foreach ($foods['foods'] as [$food, $portion, $amount])
                            <li class="flex items-center justify-between gap-2 py-2 text-sm">
                                <span class="min-w-0 text-slate-700">{{ $food }} <span class="text-xs text-slate-400">· {{ $portion }}</span></span>
                                <span class="shrink-0 font-semibold" style="color: {{ $color === '#94a3b8' ? '#64748b' : $color }}">{{ $amount }} g</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">💡 {{ $foods['tip'] }}</p>
                    <p class="mt-2 text-[11px] text-slate-400">Valores aproximados por porção.</p>

                    <button type="button" @click="nutrient = null" class="btn-primary mt-4 w-full">Fechar</button>
                </div>
            @endforeach
        </div>
    </div>
</template>
