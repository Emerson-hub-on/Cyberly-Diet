@php
    $n = $nutrition;
    $missingInfo = \App\Services\NutritionGoals::missing($n['inputs']);
    $own = collect(\App\Services\NutritionGoals::FIELDS)->map(fn ($column) => auth()->user()->{$column})->all();

    // [rótulo, unidade, coluna, passo, o que é, como o sistema calcula]
    $fields = [
        'Gasto basal' => [
            'bmr' => ['Caloria basal', 'kcal', 'bmr_kcal', 1,
                'O que seu corpo gasta num dia sem exercício: o funcionamento em repouso (respirar, bater o coração, manter a temperatura), a digestão e a rotina (andar pela casa, trabalhar). Muda conforme sexo, idade, altura e peso. Os exercícios são somados à parte, pelas atividades que você registra.',
                'Taxa metabólica basal pela fórmula de Mifflin-St Jeor (10 × peso + 6,25 × altura − 5 × idade, +5 para homens ou −161 para mulheres) × 1,2 para incluir digestão e rotina. Se o nutricionista passou um valor, digite aqui: ele é usado como está, sem nenhum acréscimo.'],
        ],
        'Objetivos' => [
            'weight' => ['Peso meta', 'kg', 'weight_goal_kg', 0.1,
                'O peso que você quer alcançar. É usado na previsão da aba Peso corporal e no cálculo da proteína.',
                'O peso mais próximo do atual dentro da faixa saudável de IMC (18,5 a 24,9) para a sua altura.'],
            'water' => ['Água/dia', 'ml', 'water_goal_ml', 50,
                'Quanto de água beber por dia. É a meta do anel da aba Ingestão de água.',
                '35 ml por kg de peso corporal.'],
            'calories' => ['Calorias/dia', 'kcal', 'calorie_goal', 10,
                'Quanto comer por dia. É a meta da barra de Calorias consumidas e da previsão de peso.',
                'A caloria basal. Se o peso meta for menor que o atual, tira 500 kcal (perde cerca de 0,5 kg por semana). Se for maior, soma 300 kcal. Nunca fica abaixo de 1.200 kcal (mulheres) ou 1.500 kcal (homens).'],
        ],
        'Macronutrientes por dia' => [
            'protein' => ['Proteína', 'g', 'protein_goal_g', 1,
                'Ajuda a manter os músculos e dá saciedade. Está em carnes, ovos, leite, feijão.',
                '1,6 g por kg do peso meta, limitado a 35% das calorias do dia.'],
            'carbs' => ['Carboidrato', 'g', 'carbs_goal_g', 1,
                'A principal fonte de energia. Está em arroz, pães, massas, frutas, tubérculos.',
                'As calorias que sobram depois da proteína e da gordura (cada grama de carboidrato tem 4 kcal).'],
            'fat' => ['Gorduras', 'g', 'fat_goal_g', 1,
                'Necessária para hormônios e para absorver vitaminas. Está em azeite, castanhas, abacate, carnes.',
                '30% das calorias do dia (cada grama de gordura tem 9 kcal).'],
            'fiber' => ['Fibras', 'g', 'fiber_goal_g', 1,
                'Ajudam o intestino e dão saciedade. Estão em verduras, frutas, grãos integrais, feijão.',
                '14 g a cada 1.000 kcal da meta de calorias, com mínimo de 25 g por dia.'],
        ],
    ];
    $kcalPerGram = ['protein' => 4, 'carbs' => 4, 'fat' => 9];
    $help = collect($fields)->collapse()->map(fn ($f) => ['title' => $f[0], 'unit' => $f[1], 'what' => $f[4], 'how' => $f[5]])->all();
@endphp

<section x-show="tab === 'metas'" x-cloak class="space-y-4">
    @if ($missingInfo)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Para o cálculo automático ficar certo, falta: <b>{{ implode(', ', $missingInfo) }}</b>.
            @if (array_diff($missingInfo, ['peso']))
                <a href="{{ route('profile') }}" class="font-semibold underline">Preencher no perfil →</a>
            @endif
            @if (in_array('peso', $missingInfo, true))
                <button type="button" class="font-semibold underline" @click="tab = 'peso'">Registrar peso →</button>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('goals.update') }}" class="card space-y-5"
          x-data="goalsForm(@js(['inputs' => $n['inputs'], 'own' => $own]))">
        @csrf @method('PUT')
        <input type="hidden" name="auto" :value="autoList">

        <div>
            <h2 class="text-lg font-bold text-slate-800">Metas</h2>
            <p class="text-sm text-slate-500">Calculadas pelo seu perfil. Você pode alterar qualquer valor.</p>
        </div>

        @if ($errors->any())
            <p class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first() }}</p>
        @endif

        <div x-data="{ help: null, info: @js($help) }">
            @foreach ($fields as $group => $items)
                <div @class(['mt-5' => ! $loop->first])>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $group }}</h3>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ($items as $key => [$label, $unit, $name])
                            <div @class(['col-span-2' => $key === 'bmr'])>
                                <div class="mb-1 flex items-center gap-1.5">
                                    <label class="text-sm font-medium text-slate-600" for="goal-{{ $key }}">{{ $label }} <span class="font-normal text-slate-400">({{ $unit }})</span></label>
                                    <button type="button" @click="help = '{{ $key }}'" aria-label="O que é {{ $label }}?"
                                            class="flex size-5 shrink-0 items-center justify-center rounded-full bg-amber-400 text-xs font-bold text-white shadow-sm transition hover:bg-amber-500 active:scale-90">?</button>
                                </div>
                                <input id="goal-{{ $key }}" name="{{ $name }}" type="number" step="{{ $items[$key][3] }}" min="0" inputmode="decimal" class="input"
                                       :value="show('{{ $key }}')" @input="set('{{ $key }}', $event.target.value)"
                                       value="{{ $n['values'][$key] }}">
                                <p class="mt-1 text-[11px] leading-snug">
                                    <span x-show="auto.{{ $key }}" class="font-semibold text-emerald-600">✓ Automático</span>
                                    <span x-show="!auto.{{ $key }}" x-cloak class="text-navy-600">
                                        Personalizado ·
                                        <button type="button" class="font-semibold underline" @click="reset('{{ $key }}')">
                                            usar automático (<span x-text="fmt(suggest('{{ $key }}'))"></span>)
                                        </button>
                                    </span>
                                    @isset($kcalPerGram[$key])
                                        <span class="text-slate-400" x-text="' · ' + macroPct('{{ $key }}', {{ $kcalPerGram[$key] }}) + '% das calorias'"></span>
                                    @endisset
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- Modal de ajuda (aberto pelo "?" de cada campo) --}}
            <template x-teleport="body">
                <div x-show="help" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center"
                     @click.self="help = null" @keydown.escape.window="help = null" role="dialog" aria-modal="true">
                    <div x-show="help" x-transition class="w-full max-w-md rounded-3xl bg-white p-5 shadow-xl">
                        <template x-if="help">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-amber-400 text-sm font-bold text-white">?</span>
                                        <h3 class="text-lg font-bold text-slate-800" x-text="info[help].title"></h3>
                                    </div>
                                    <button type="button" @click="help = null" class="rounded-full p-1 text-slate-400 hover:bg-ice-100 hover:text-slate-600" aria-label="Fechar">
                                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>

                                <h4 class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">O que é</h4>
                                <p class="mt-1 text-sm text-slate-700" x-text="info[help].what"></p>

                                <h4 class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Como o sistema calcula</h4>
                                <p class="mt-1 text-sm text-slate-700" x-text="info[help].how"></p>

                                <div class="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-800" x-show="suggest(help) !== null">
                                    Sugestão para você: <b x-text="fmt(suggest(help)) + ' ' + info[help].unit"></b>
                                </div>
                                <p class="mt-3 text-xs text-slate-400">Se o seu nutricionista indicou outro valor, é só digitar no campo.</p>

                                <button type="button" @click="help = null" class="btn-primary mt-4 w-full">Entendi</button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <div class="rounded-xl bg-ice-50 px-3 py-2 text-xs text-slate-500" x-show="macroKcal() > 0">
            Proteínas + carboidratos + gorduras = <b class="text-slate-700" x-text="fmt(macroKcal()) + ' kcal'"></b>
            de <b class="text-slate-700" x-text="fmt(show('calories')) + ' kcal'"></b> da meta.
        </div>

        <button class="btn-primary w-full">Salvar metas</button>
        <button type="button" class="w-full text-center text-sm font-semibold text-navy-600" @click="resetAll()" x-show="Object.values(auto).some((v) => !v)">
            Voltar tudo para o automático
        </button>
    </form>
</section>
