@php
    $p = $progress;
    $fmt = fn ($v, $d = 1) => number_format((float) $v, $d, ',', '.');
    $losing = $p['goal'] && $p['current'] && $p['goal'] < $p['current'];
    $trend = $p['trend'];
    // "Progredindo" depende do objetivo: perder (meta abaixo) ou ganhar (meta acima)
    $trendGood = $trend && (($losing && $trend['status'] === 'perdendo') || (! $losing && $trend['status'] === 'ganhando'));
    $humanDays = function (?int $days) {
        if ($days === null) return null;
        if ($days < 14) return $days . ' dias';
        if ($days < 90) return round($days / 7) . ' semanas';
        $months = round($days / 30.4, 1);
        return number_format($months, $months < 24 ? 1 : 0, ',', '.') . ' meses';
    };
@endphp

@php
    $showForecast = isset($p['planned']) && ! $p['reached'];
    $slideCount = 1 + ($showForecast ? 1 : 0) + (isset($p['weeks']) ? 1 : 0) + ($weights->isNotEmpty() ? 2 : 0);
@endphp

<section x-show="tab === 'peso'" x-cloak class="space-y-4">

    {{-- Carrossel: deslize para o lado para ver previsão, progressão e gráficos --}}
    <div x-data="carousel">
    <div x-ref="slides" @scroll.debounce.50ms="onScroll()"
         class="no-scrollbar -my-2 flex snap-x snap-mandatory items-start gap-3 overflow-x-auto overflow-y-hidden py-2 transition-[height] duration-300">

    {{-- Peso atual x meta --}}
    <div class="w-full shrink-0 snap-start space-y-4">
    <div class="card">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Peso atual</h2>
                <p class="mt-0.5 text-3xl font-light text-slate-800">{{ $p['current'] ? $fmt($p['current']) . ' kg' : '—' }}</p>
            </div>
            <div class="text-right">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Meta de peso</h2>
                <p class="mt-0.5 text-3xl font-light text-navy-700">{{ $p['goal'] ? $fmt($p['goal']) . ' kg' : '—' }}</p>
            </div>
        </div>

        @if ($p['current'] && $p['goal'])
            <div class="mt-4">
                <div class="h-3 overflow-hidden rounded-full bg-ice-100">
                    <div class="h-full rounded-full bg-gradient-to-r from-navy-600 to-sky-400 transition-all" style="width: {{ $p['progress_pct'] }}%"></div>
                </div>
                <div class="mt-2 flex justify-between text-xs text-slate-500">
                    <span>Início {{ $fmt($p['start']) }} kg · {{ $p['start_date']?->format('d/m/Y') }}</span>
                    <span class="font-semibold text-navy-700">{{ $p['progress_pct'] }}% do caminho</span>
                </div>
                <p class="mt-3 text-sm text-slate-600">
                    @if ($p['reached'])
                        🎉 <b>Meta atingida!</b>
                    @else
                        Faltam <b class="text-slate-800">{{ $fmt(abs($p['to_go_kg'])) }} kg</b> para {{ $losing ? 'perder' : 'ganhar' }}.
                    @endif
                </p>
            </div>
        @endif

        <div class="mt-3 flex flex-wrap gap-2 text-xs">
            @if ($trend)
                <span @class([
                    'rounded-full px-2.5 py-1 font-semibold',
                    'bg-emerald-50 text-emerald-700' => $trendGood,
                    'bg-rose-50 text-rose-700' => ! $trendGood && $trend['status'] !== 'estavel',
                    'bg-slate-100 text-slate-600' => $trend['status'] === 'estavel',
                ])>
                    {{ $trendGood ? '▼ Progredindo' : ($trend['status'] === 'estavel' ? '● Estável' : '▲ ' . ($losing ? 'Aumentando' : 'Diminuindo')) }}
                    · {{ $trend['diff_kg'] > 0 ? '+' : '' }}{{ $fmt($trend['diff_kg']) }} kg em {{ $trend['days'] }} {{ $trend['days'] === 1 ? 'dia' : 'dias' }}
                </span>
            @endif
            @if ($bmi)
                <span class="rounded-full bg-ice-100 px-2.5 py-1 text-slate-600">IMC {{ $fmt($bmi) }}</span>
            @endif
        </div>
    </div>

    {{-- Dados faltando para o cálculo --}}
    @if ($p['missing'])
        <a href="{{ route('profile') }}" class="block rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Para calcular o déficit e a previsão, preencha: <b>{{ implode(', ', $p['missing']) }}</b>.
            <span class="font-semibold underline">Ir para o perfil →</span>
        </a>
    @endif
    </div>

    {{-- Previsão até a meta --}}
    @if ($showForecast)
        <div class="card w-full shrink-0 snap-start">
            <h2 class="text-lg font-bold text-slate-800">Previsão para atingir a meta</h2>

            {{-- Déficit real do dia selecionado --}}
            <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Balanço de {{ $date->isToday() ? 'hoje' : $date->format('d/m/Y') }}</p>
            <dl class="mt-1.5 space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Gasto basal (sem exercício)</dt><dd class="font-medium text-slate-700">{{ $fmt($p['base_expenditure'], 0) }} kcal</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">+ Gasto em atividades</dt><dd class="font-medium text-slate-700">{{ $fmt($p['day']['activity'], 0) }} kcal</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">− Calorias consumidas</dt><dd class="font-medium text-slate-700">{{ $fmt($p['day']['intake'], 0) }} kcal</dd></div>
                <div class="flex justify-between border-t border-ice-100 pt-1.5">
                    <dt class="font-semibold text-slate-700">{{ $p['day']['deficit'] >= 0 ? 'Déficit' : 'Superávit' }} do dia</dt>
                    <dd class="font-bold {{ $p['day']['deficit'] === 0 ? 'text-slate-500' : (($p['day']['deficit'] > 0) === $losing ? 'text-emerald-600' : 'text-rose-600') }}">{{ $fmt(abs($p['day']['deficit']), 0) }} kcal</dd>
                </div>
            </dl>
            @if (! $p['day']['intake'])
                <p class="mt-1.5 text-xs text-amber-700">Nenhuma refeição registrada neste dia. O déficit é calculado assim que você registrar o que comeu.</p>
            @endif

            <div class="mt-4 rounded-xl bg-navy-50 p-4">
                @if ($p['planned']['days'])
                    <p class="text-xs font-semibold uppercase tracking-wide text-navy-600">Mantendo a meta de {{ $fmt($p['intake_goal'], 0) }} kcal/dia</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $p['planned_deficit'] >= 0 ? 'Déficit' : 'Superávit' }} planejado de {{ $fmt(abs($p['planned_deficit']), 0) }} kcal/dia (atividades na média de 7 dias: {{ $fmt($p['avg_activity'], 0) }} kcal)</p>
                    <p class="mt-1 text-2xl font-light text-navy-800">≈ {{ $humanDays($p['planned']['days']) }}</p>
                    <p class="text-sm text-slate-600">Previsão: <b>{{ $p['planned']['date']->translatedFormat('F \d\e Y') }}</b> ({{ $p['planned']['date']->format('d/m/Y') }})</p>
                    <p class="mt-1 text-xs text-slate-500">≈ {{ $fmt(abs($p['planned_deficit']) * 7 / config('diet.kcal_per_kg'), 2) }} kg por semana no ritmo atual</p>
                @else
                    <p class="text-sm font-semibold text-rose-700">Com essa meta de calorias você não chega a {{ $fmt($p['goal']) }} kg.</p>
                    <p class="mt-1 text-sm text-slate-600">Seu peso tende a estabilizar em torno de <b>{{ $fmt($p['planned']['equilibrium_kg']) }} kg</b>. Ajuste a meta de calorias ou aumente as atividades.</p>
                @endif
            </div>

            @if ($p['real'])
                <div class="mt-3 rounded-xl border border-ice-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pelo que você realmente comeu ({{ $p['real']['days_logged'] }} dias registrados)</p>
                    <p class="mt-1 text-sm text-slate-600">
                        Consumo médio <b>{{ $fmt($p['real']['avg_intake'], 0) }} kcal</b> ·
                        {{ $p['real']['deficit'] >= 0 ? 'déficit' : 'superávit' }} de <b>{{ $fmt(abs($p['real']['deficit']), 0) }} kcal/dia</b>
                    </p>
                    @if ($p['real']['days'])
                        <p class="mt-1 text-lg font-light text-slate-800">≈ {{ $humanDays($p['real']['days']) }} <span class="text-sm text-slate-500">({{ $p['real']['date']->format('d/m/Y') }})</span></p>
                    @else
                        <p class="mt-1 text-sm text-rose-700">Nesse ritmo o peso estabiliza perto de {{ $fmt($p['real']['equilibrium_kg']) }} kg.</p>
                    @endif
                </div>
            @endif

            <p class="mt-3 text-xs text-slate-400">
                Estimativa: metabolismo basal por Mifflin-St Jeor (TMB {{ $fmt($p['bmr'], 0) }} kcal) × 1,2, mais as atividades registradas;
                1 kg ≈ {{ $fmt(config('diet.kcal_per_kg'), 0) }} kcal. O gasto é recalculado conforme o peso cai.
            </p>
        </div>
    @endif

    {{-- Progressão: balanço calórico x balança --}}
    @isset($p['weeks'])
        <div class="card w-full shrink-0 snap-start">
            <h2 class="text-lg font-bold text-slate-800">Progressão</h2>

            <div class="mt-3 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-ice-50 p-3">
                    <p class="text-xs text-slate-500">Esperado pelo balanço calórico</p>
                    <p class="text-xl font-light text-slate-800">{{ $p['expected_change_kg'] > 0 ? '+' : '' }}{{ $fmt($p['expected_change_kg']) }} kg</p>
                    <p class="text-[11px] text-slate-400">{{ $p['logged_days_since_start'] }} dias com refeições</p>
                </div>
                <div class="rounded-xl bg-ice-50 p-3">
                    <p class="text-xs text-slate-500">Real na balança</p>
                    <p class="text-xl font-light {{ ($p['actual_change_kg'] < 0) === $losing && $p['actual_change_kg'] != 0 ? 'text-emerald-600' : 'text-slate-800' }}">{{ $p['actual_change_kg'] > 0 ? '+' : '' }}{{ $fmt($p['actual_change_kg']) }} kg</p>
                    <p class="text-[11px] text-slate-400">desde {{ $p['start_date']?->format('d/m') }}</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-slate-400">
                        <tr>
                            <th class="pb-2 font-medium">Semana</th>
                            <th class="pb-2 text-right font-medium">Consumo/dia</th>
                            <th class="pb-2 text-right font-medium">Atividades</th>
                            <th class="pb-2 text-right font-medium">Déficit</th>
                            <th class="pb-2 text-right font-medium">Peso</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ice-100">
                        @foreach ($p['weeks'] as $w)
                            <tr>
                                <td class="py-2 text-slate-600">{{ $w['label'] }}<br><span class="text-[10px] text-slate-400">{{ $w['days_logged'] }}/7 dias</span></td>
                                <td class="py-2 text-right text-slate-700">{{ $w['avg_intake'] ? $fmt($w['avg_intake'], 0) : '—' }}</td>
                                <td class="py-2 text-right text-slate-700">{{ $w['activity'] ? '−' . $fmt($w['activity'], 0) : '—' }}</td>
                                <td class="py-2 text-right">
                                    @if ($w['days_logged'])
                                        <span class="{{ $w['deficit'] > 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $fmt($w['deficit'], 0) }}</span>
                                        <br><span class="text-[10px] text-slate-400">≈ {{ $w['expected_kg'] > 0 ? '+' : '' }}{{ $fmt($w['expected_kg'], 2) }} kg</span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right text-slate-700">
                                    {{ $w['weight'] ? $fmt($w['weight']) : '—' }}
                                    @if ($w['change_kg'] !== null)
                                        <br><span class="text-[10px] {{ ($w['change_kg'] < 0) === $losing && $w['change_kg'] != 0 ? 'text-emerald-600' : 'text-rose-500' }}">{{ $w['change_kg'] > 0 ? '+' : '' }}{{ $fmt($w['change_kg']) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-400">Déficit = gasto basal + atividades − consumo, somado só nos dias com refeições registradas. Registre todas as refeições para a estimativa ficar fiel.</p>
        </div>
    @endisset

    @if ($weights->isNotEmpty())
        {{-- Gráfico: evolução --}}
        <div class="card w-full shrink-0 snap-start">
            <h2 class="mb-3 text-lg font-bold text-slate-800">Evolução do peso (kg)</h2>
            <div class="h-56"><canvas id="chart-weight"></canvas></div>
        </div>

        {{-- Gráfico: variação a cada registro --}}
        <div class="card w-full shrink-0 snap-start">
            <h2 class="text-lg font-bold text-slate-800">Variação diária (kg)</h2>
            <p class="mb-3 text-xs text-slate-500">
                Barra <span class="font-semibold text-emerald-600">verde</span> = {{ $losing || ! $p['goal'] ? 'baixou' : 'subiu' }} (rumo à meta) ·
                <span class="font-semibold text-rose-500">vermelha</span> = {{ $losing || ! $p['goal'] ? 'aumentou' : 'baixou' }}
            </p>
            <div class="h-48"><canvas id="chart-weight-diff"></canvas></div>
        </div>
    @endif
    </div>

    @if ($slideCount > 1)
        <div class="mt-3 flex items-center justify-center gap-1">
            @for ($i = 0; $i < $slideCount; $i++)
                <button type="button" class="p-1" aria-label="Card {{ $i + 1 }} de {{ $slideCount }}" @click="go({{ $i }})">
                    <span class="block h-2 rounded-full transition-all" :class="page === {{ $i }} ? 'w-5 bg-navy-600' : 'w-2 bg-ice-200'"></span>
                </button>
            @endfor
        </div>
        <p x-show="page === 0" class="mt-1 text-center text-[11px] text-slate-400">Deslize para o lado para ver previsão, progressão e gráficos →</p>
    @endif
    </div>

    {{-- Registrar peso (já preenchido com o último registro salvo) --}}
    @php($todayLog = $weights->first(fn ($w) => $w->measured_on->isSameDay($date)))
    <form method="POST" action="{{ route('weight.store') }}" class="card space-y-3">
        @csrf
        <h2 class="font-bold text-slate-800">Registrar peso</h2>
        @if ($latestWeight)
            <p class="-mt-1 text-xs text-slate-500">
                Último registro: <b class="text-slate-700">{{ $fmt($latestWeight->weight_kg) }} kg</b> em {{ $latestWeight->measured_on->format('d/m/Y') }}
            </p>
        @endif
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label" for="weight_kg">Peso (kg)</label>
                <input id="weight_kg" name="weight_kg" type="number" step="0.1" min="20" max="400" inputmode="decimal" class="input" required
                       value="{{ old('weight_kg', $latestWeight ? round($latestWeight->weight_kg, 1) : '') }}">
            </div>
            <div>
                <label class="label" for="measured_on">Data</label>
                <input id="measured_on" name="measured_on" type="date" class="input" value="{{ old('measured_on', $date->toDateString()) }}" required>
            </div>
        </div>
        <button class="btn-primary w-full">{{ $todayLog ? 'Atualizar peso do dia' : 'Salvar peso' }}</button>
        <p class="text-xs text-slate-400">Um registro por dia: salvar de novo na mesma data atualiza o peso daquele dia.</p>
    </form>

    @if ($weights->isEmpty())
        <div class="card py-8 text-center text-sm text-slate-400">Registre seu peso para ver o histórico e os gráficos.</div>
    @else
        {{-- Histórico diário --}}
        <div class="card" x-data="{ all: false }">
            <h2 class="mb-2 text-lg font-bold text-slate-800">Histórico de peso diário</h2>
            <ul class="divide-y divide-ice-100 text-sm">
                @foreach ($weightHistory->reverse()->values() as $i => $h)
                    @php([$w, $d] = [$h['log'], $h['diff']])
                    @php($good = $d !== null && $d != 0 && (($d < 0) === ($losing || ! $p['goal'])))
                    <li class="flex items-center justify-between gap-3 py-2.5" @if ($i >= 10) x-show="all" x-cloak @endif>
                        <div class="min-w-0">
                            <p class="font-medium text-slate-700">{{ ucfirst($w->measured_on->translatedFormat('D')) }}, {{ $w->measured_on->format('d/m/Y') }}</p>
                            @if ($h['days'] && $h['days'] > 1)
                                <p class="text-[11px] text-slate-400">{{ $h['days'] }} dias desde o registro anterior</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            @if ($d === null)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">início</span>
                            @elseif ($d == 0)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500">= 0,0</span>
                            @else
                                <span @class(['rounded-full px-2 py-0.5 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $good, 'bg-rose-50 text-rose-600' => ! $good])>
                                    {{ $d < 0 ? '▼' : '▲' }} {{ $d > 0 ? '+' : '' }}{{ $fmt($d) }}
                                </span>
                            @endif
                            <span class="w-16 text-right font-semibold text-slate-800">{{ $fmt($w->weight_kg) }} kg</span>
                            <form method="POST" action="{{ route('weight.destroy', $w) }}" onsubmit="return confirm('Remover o registro de {{ $w->measured_on->format('d/m') }}?')">
                                @csrf @method('DELETE')
                                <button class="text-slate-300 hover:text-rose-500" aria-label="Remover">✕</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($weightHistory->count() > 10)
                <button type="button" class="mt-2 w-full text-center text-sm font-semibold text-navy-600" @click="all = !all"
                        x-text="all ? 'Mostrar menos ▲' : 'Ver todos os {{ $weightHistory->count() }} registros ▼'"></button>
            @endif
        </div>
    @endif
</section>
