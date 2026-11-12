@php
    $prev = $date->subDay()->toDateString();
    $next = $date->addDay()->toDateString();
    $dateLabel = match (true) {
        $date->isToday() => 'Hoje',
        $date->isYesterday() => 'Ontem',
        $date->isTomorrow() => 'Amanhã',
        default => ucfirst($date->translatedFormat('l')),
    } . ' - ' . $date->format('d/m/Y');
    $remaining = max($goal - $totals['calories'], 0);
@endphp

<x-layouts.app title="Evolução">
    <div x-data="dashboard('{{ $tab }}', @js($charts))" class="space-y-4">

        {{-- Faixa de destaque --}}
        <div class="rounded-xl border border-navy-100 bg-navy-50 px-4 py-2.5 text-center text-sm font-medium text-navy-700">
            Meta diária: {{ number_format($goal, 0, ',', '.') }} kcal · {{ number_format($waterGoal / 1000, 1, ',', '.') }} L de água
        </div>

        {{-- Abas --}}
        <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
            @foreach (['calorias' => 'Consumo calórico', 'peso' => 'Peso corporal', 'atividade' => 'Atividade física', 'agua' => 'Ingestão de água', 'metas' => 'Metas'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'" class="chip" :class="tab === '{{ $key }}' && 'chip-active'">{{ $label }}</button>
            @endforeach
        </div>

        {{-- Navegação por data --}}
        <div class="flex items-center justify-between px-1">
            <a :href="link('{{ $prev }}')" class="rounded-full p-1.5 text-navy-600 hover:bg-white" aria-label="Dia anterior">
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
            <a :href="link('{{ $next }}')" class="rounded-full p-1.5 text-navy-600 hover:bg-white" aria-label="Próximo dia">
                <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </a>
        </div>

        {{-- ==================== CONSUMO CALÓRICO ==================== --}}
        <section x-show="tab === 'calorias'" class="space-y-4">
            {{-- Carrossel: Calorias consumidas → Histórico de calorias --}}
            <div x-data="carousel">
            <div x-ref="slides" @scroll.debounce.50ms="onScroll()"
                 class="no-scrollbar -my-2 flex snap-x snap-mandatory items-start gap-3 overflow-x-auto overflow-y-hidden py-2 transition-[height] duration-300">
            <div class="card w-full shrink-0 snap-start">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">Calorias consumidas</h2>
                        <p class="mt-1 text-3xl font-light text-slate-800">{{ number_format($totals['calories'], 0, ',', '.') }} Kcal</p>
                    </div>
                    <a href="?tab=metas" @click.prevent="tab = 'metas'" class="rounded-full bg-ice-100 p-2 text-slate-500 hover:text-navy-700" aria-label="Configurar metas">
                        <svg class="size-5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567L9.05 4.889c-.02.12-.115.26-.297.348a7.493 7.493 0 0 0-.986.57c-.166.115-.334.126-.45.083L6.3 5.508a1.875 1.875 0 0 0-2.282.819l-.922 1.597a1.875 1.875 0 0 0 .432 2.385l.84.692c.095.078.17.229.154.43a7.598 7.598 0 0 0 0 1.139c.015.2-.059.352-.153.43l-.841.692a1.875 1.875 0 0 0-.432 2.385l.922 1.597a1.875 1.875 0 0 0 2.282.818l1.019-.382c.115-.043.283-.031.45.082.312.214.641.405.985.57.182.088.277.228.297.35l.178 1.071c.151.904.933 1.567 1.85 1.567h1.844c.916 0 1.699-.663 1.85-1.567l.178-1.072c.02-.12.114-.26.297-.349.344-.165.673-.356.985-.57.167-.114.335-.125.45-.082l1.02.382a1.875 1.875 0 0 0 2.28-.819l.923-1.597a1.875 1.875 0 0 0-.432-2.385l-.84-.692c-.095-.078-.17-.229-.154-.43a7.614 7.614 0 0 0 0-1.139c-.016-.2.059-.352.153-.43l.84-.692c.708-.582.891-1.59.433-2.385l-.922-1.597a1.875 1.875 0 0 0-2.282-.818l-1.02.382c-.114.043-.282.031-.449-.083a7.49 7.49 0 0 0-.985-.57c-.183-.087-.277-.227-.297-.348l-.179-1.072a1.875 1.875 0 0 0-1.85-1.567h-1.843ZM12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" clip-rule="evenodd"/></svg>
                    </a>
                </div>
                <div class="mt-4">
                    <div class="h-2.5 overflow-hidden rounded-full bg-ice-100">
                        <div class="h-full rounded-full {{ $goalPct > 100 ? 'bg-rose-400' : 'bg-gradient-to-r from-navy-600 to-sky-400' }}"
                             style="width: {{ min($goalPct, 100) }}%"></div>
                    </div>
                    <div class="mt-2 flex justify-between text-xs text-slate-500">
                        <span>{{ $goalPct }}% da meta</span>
                        <span>{{ $goalPct > 100 ? 'Excedeu ' . number_format($totals['calories'] - $goal, 0, ',', '.') : 'Restam ' . number_format($remaining, 0, ',', '.') }} kcal</span>
                    </div>
                </div>
            </div>

            <div class="card w-full shrink-0 snap-start">
                <h2 class="mb-3 text-lg font-bold text-slate-800">Histórico de calorias (Kcal)</h2>
                <div class="h-56"><canvas id="chart-calories"></canvas></div>
            </div>
            </div>

            <x-carousel-dots :labels="['Calorias consumidas', 'Histórico de calorias']" hint="Deslize para o lado para ver o histórico de calorias →" />
            </div>

            <div class="card">
                @php
                    $grams = fn ($v) => rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',');
                    $fiberGoal = $nutrition['values']['fiber'];
                    $sugarLimit = round($goal * config('diet.sugar_limit_pct') / 100 / 4); // 1 g de açúcar = 4 kcal
                    $fiberPct = $fiberGoal > 0 ? (int) round($totals['fiber_g'] / $fiberGoal * 100) : 0;
                    $sugarPct = $sugarLimit > 0 ? (int) round($totals['sugar_g'] / $sugarLimit * 100) : 0;
                @endphp
                <div x-data="{ page: 0 }">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-800">Nutrientes do dia</h2>
                    <div class="flex gap-1">
                        @foreach (['Macronutrientes', 'Açúcar e fibras'] as $i => $pageLabel)
                            <button type="button" aria-label="{{ $pageLabel }}" class="p-1"
                                    @click="$refs.slides.scrollTo({ left: {{ $i }} * $refs.slides.clientWidth, behavior: 'smooth' })">
                                <span class="block size-2 rounded-full transition-colors" :class="page === {{ $i }} ? 'bg-navy-600' : 'bg-ice-200'"></span>
                            </button>
                        @endforeach
                    </div>
                </div>
                {{-- Deslize para o lado: macronutrientes ↔ açúcar e fibras --}}
                <div x-ref="slides" @scroll.debounce.50ms="page = Math.round($el.scrollLeft / $el.clientWidth)"
                     class="no-scrollbar -mx-1 flex snap-x snap-mandatory overflow-x-auto">
                <div class="flex w-full shrink-0 snap-start justify-between px-1">
                    @foreach ([['protein', 'protein_g', '#3b82f6', 'Proteínas'], ['carbs', 'carbs_g', '#06b6d4', 'Carbos'], ['fat', 'fat_g', '#6366f1', 'Gorduras']] as [$key, $col, $color, $label])
                        @php($target = $nutrition['values'][$key])
                        @php($pct = $target > 0 ? (int) round($totals[$col] / $target * 100) : 0)
                        <x-ring :pct="$pct" :color="$color" :label="$label">
                            <span class="font-bold text-slate-800">{{ $grams($totals[$col]) }}g</span>
                            <span class="text-[11px] {{ $pct > 110 ? 'text-rose-500' : 'text-slate-400' }}">de {{ $target }}g</span>
                        </x-ring>
                    @endforeach
                </div>
                <div class="flex w-full shrink-0 snap-start justify-around px-1">
                    <x-ring :pct="$sugarPct" :color="$sugarPct > 100 ? '#f43f5e' : '#94a3b8'" label="Açúcar">
                        <span class="font-bold text-slate-800">{{ $grams($totals['sugar_g']) }}g</span>
                        <span class="text-[11px] {{ $sugarPct > 100 ? 'text-rose-500' : 'text-slate-400' }}">de {{ $sugarLimit }}g</span>
                    </x-ring>
                    <x-ring :pct="$fiberPct" color="#10b981" label="Fibras">
                        <span class="font-bold text-slate-800">{{ $grams($totals['fiber_g']) }}g</span>
                        <span class="text-[11px] {{ $fiberPct >= 100 ? 'text-emerald-600' : 'text-slate-400' }}">de {{ $fiberGoal }}g</span>
                    </x-ring>
                </div>
                </div>
                <p x-show="page === 0" class="mt-3 text-xs text-slate-400">Quanto você já consumiu das suas <button type="button" class="font-semibold text-navy-600 underline" @click="tab = 'metas'">metas</button> do dia. Deslize para ver açúcar e fibras →</p>
                <p x-show="page === 1" x-cloak class="mt-3 text-xs text-slate-400">
                    Açúcar: limite de {{ config('diet.sugar_limit_pct') }}% da meta de calorias ({{ $sugarLimit }} g){{ $sugarPct > 100 ? ' · ultrapassou em ' . $grams($totals['sugar_g'] - $sugarLimit) . ' g' : '' }}.
                    Fibras: meta de {{ $fiberGoal }} g por dia.
                </p>
                </div>
            </div>

            <div class="card">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-800">Refeições do dia</h2>
                    <button type="button" class="text-sm font-semibold text-navy-600" @click="$store.mealModal.show()">+ Adicionar</button>
                </div>

                @forelse ($meals as $meal)
                    <x-meal-card :meal="$meal" class="border-t border-ice-100 py-2 first:border-t-0 first:pt-0" />
                @empty
                    <div class="py-6 text-center text-sm text-slate-400">
                        Nenhuma refeição registrada neste dia.<br>Toque no <b class="text-navy-600">+</b> e fotografe seu prato.
                    </div>
                @endforelse
            </div>
        </section>

        @include('partials.tab-weight')

        @include('partials.tab-goals')

        {{-- ==================== ATIVIDADE FÍSICA ==================== --}}
        <section x-show="tab === 'atividade'" x-cloak class="space-y-4">
            {{-- Carrossel: Atividade do dia → Últimos 7 dias --}}
            <div x-data="carousel">
            <div x-ref="slides" @scroll.debounce.50ms="onScroll()"
                 class="no-scrollbar -my-2 flex snap-x snap-mandatory items-start gap-3 overflow-x-auto overflow-y-hidden py-2 transition-[height] duration-300">
            <div class="card w-full shrink-0 snap-start">
                <h2 class="text-lg font-bold text-slate-800">Atividade do dia</h2>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-indigo-50 p-3">
                        <p class="text-2xl font-light text-slate-800">{{ $activities->sum('duration_min') }} <span class="text-base">min</span></p>
                        <p class="text-xs text-slate-500">Tempo ativo</p>
                    </div>
                    <div class="rounded-xl bg-sky-50 p-3">
                        <p class="text-2xl font-light text-slate-800">{{ number_format($activities->sum('calories_burned'), 0, ',', '.') }} <span class="text-base">kcal</span></p>
                        <p class="text-xs text-slate-500">Gasto estimado</p>
                    </div>
                </div>
                @if ($activities->isNotEmpty())
                    <ul class="mt-4 divide-y divide-ice-100 text-sm">
                        @foreach ($activities as $a)
                            <li class="flex items-center justify-between py-2">
                                <span class="font-medium text-slate-700">{{ $a->activity }}</span>
                                <span class="flex items-center gap-3 text-slate-500">{{ $a->duration_min }} min · <b class="text-indigo-600">−{{ $a->calories_burned }} kcal</b>
                                    <form method="POST" action="{{ route('activity.destroy', $a) }}" onsubmit="return confirm('Remover esta atividade?')">@csrf @method('DELETE')<button class="text-slate-300 hover:text-rose-500" aria-label="Remover">✕</button></form>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="card w-full shrink-0 snap-start">
                <h2 class="mb-3 text-lg font-bold text-slate-800">Últimos 7 dias</h2>
                <div class="h-56"><canvas id="chart-activity"></canvas></div>
            </div>
            </div>
            <x-carousel-dots :labels="['Atividade do dia', 'Últimos 7 dias']" hint="Deslize para o lado para ver os últimos 7 dias →" />
            </div>

            <form method="POST" action="{{ route('activity.store') }}" class="card space-y-3"
                  x-data="activityForm(@js($activityMets), {{ $defaultMet }}, {{ $weightForActivity }})">
                @csrf
                <h2 class="font-bold text-slate-800">Registrar atividade</h2>
                <input type="hidden" name="performed_on" value="{{ $date->toDateString() }}">
                <div>
                    <label class="label" for="activity">Atividade</label>
                    <input id="activity" name="activity" list="activity-list" class="input" placeholder="Ex.: Academia" required maxlength="60" x-model="activity">
                    <datalist id="activity-list">
                        @foreach (array_keys($activityMets) as $opt)
                            <option value="{{ $opt }}">
                        @endforeach
                    </datalist>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="duration_min">Duração (min)</label>
                        <input id="duration_min" name="duration_min" type="number" min="1" max="1440" inputmode="numeric" class="input" required x-model.number="minutes">
                    </div>
                    <div>
                        <label class="label" for="calories_burned">Kcal gastas</label>
                        <input id="calories_burned" name="calories_burned" type="number" min="0" inputmode="numeric" class="input" :placeholder="estimate ? 'auto: ' + estimate : 'automático'">
                    </div>
                </div>
                <div x-show="estimate" x-cloak class="rounded-xl bg-indigo-50 px-3 py-2 text-sm text-indigo-800">
                    <b x-text="(activity || 'Atividade') + ': ' + minutes + ' min'"></b> → <b x-text="'−' + estimate + ' kcal'"></b>
                    <p class="text-xs text-indigo-600/80" x-text="'MET ' + met.toLocaleString('pt-BR') + ' × ' + weight.toLocaleString('pt-BR') + ' kg × ' + (minutes / 60).toLocaleString('pt-BR', {maximumFractionDigits: 2}) + ' h' + (known ? '' : ' (atividade não cadastrada: intensidade média)')"></p>
                </div>
                <p class="text-xs text-slate-400">Deixe "Kcal gastas" em branco para calcular automaticamente pelo seu peso ({{ number_format($weightForActivity, 1, ',', '.') }} kg).</p>
                <button class="btn-primary w-full">Salvar atividade</button>
            </form>

        </section>

        {{-- ==================== ÁGUA ==================== --}}
        <section x-show="tab === 'agua'" x-cloak class="space-y-4">
            @php($waterPct = $waterGoal > 0 ? round($waterTotal / $waterGoal * 100) : 0)
            {{-- Carrossel: Ingestão de água → Histórico de água --}}
            <div x-data="carousel">
            <div x-ref="slides" @scroll.debounce.50ms="onScroll()"
                 class="no-scrollbar -my-2 flex snap-x snap-mandatory items-start gap-3 overflow-x-auto overflow-y-hidden py-2 transition-[height] duration-300">
            <div class="card w-full shrink-0 snap-start flex flex-col items-center">
                <h2 class="self-start text-lg font-bold text-slate-800">Ingestão de água</h2>
                <div class="my-3">
                    <x-ring :pct="$waterPct" color="#0ea5e9" :size="150" :stroke="13">
                        <span class="text-2xl font-bold text-slate-800">{{ number_format($waterTotal / 1000, 2, ',', '.') }} L</span>
                        <span class="text-xs text-slate-400">{{ $waterPct }}% de {{ number_format($waterGoal / 1000, 1, ',', '.') }} L</span>
                    </x-ring>
                </div>
                <div class="grid w-full grid-cols-3 gap-2">
                    @foreach ([200, 300, 500] as $ml)
                        <form method="POST" action="{{ route('water.store') }}">
                            @csrf
                            <input type="hidden" name="amount_ml" value="{{ $ml }}">
                            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                            <button class="w-full rounded-xl border border-sky-200 bg-sky-50 py-3 text-sm font-semibold text-sky-700 transition hover:bg-sky-100 active:scale-95">+{{ $ml }} ml</button>
                        </form>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('water.store') }}" class="mt-3 flex w-full gap-2">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <input name="amount_ml" type="number" min="10" max="5000" inputmode="numeric" class="input" placeholder="Outra quantidade (ml)" required>
                    <button class="btn-primary shrink-0">Adicionar</button>
                </form>
            </div>

            <div class="card w-full shrink-0 snap-start">
                <h2 class="mb-3 text-lg font-bold text-slate-800">Histórico de água (ml)</h2>
                <div class="h-56"><canvas id="chart-water"></canvas></div>
            </div>
            </div>
            <x-carousel-dots :labels="['Ingestão de água', 'Histórico de água']" hint="Deslize para o lado para ver o histórico de água →" />
            </div>

            @if ($waterLogs->isNotEmpty())
                <div class="card">
                    <h2 class="mb-1 text-lg font-bold text-slate-800">Registros do dia</h2>
                    <ul class="divide-y divide-ice-100 text-sm">
                        @foreach ($waterLogs->reverse() as $w)
                            <li class="flex items-center justify-between py-2">
                                <span class="text-slate-500">{{ $w->logged_at->format('H:i') }}</span>
                                <span class="flex items-center gap-3 font-semibold text-slate-700">{{ $w->amount_ml }} ml
                                    <form method="POST" action="{{ route('water.destroy', $w) }}">@csrf @method('DELETE')<button class="text-slate-300 hover:text-rose-500" aria-label="Remover">✕</button></form>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
