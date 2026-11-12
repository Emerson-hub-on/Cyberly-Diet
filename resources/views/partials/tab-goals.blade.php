@php
    $n = $nutrition;
    $missingInfo = \App\Services\NutritionGoals::missing($n['inputs']);
    $own = collect(\App\Services\NutritionGoals::FIELDS)->map(fn ($column) => auth()->user()->{$column})->all();
    $fields = [
        'Gasto basal' => [
            'bmr' => ['Caloria basal (TMB)', 'kcal', 'bmr_kcal', 1, 'Calculada pela fórmula de Mifflin-St Jeor com seu sexo, idade, altura e peso.'],
        ],
        'Objetivos' => [
            'weight' => ['Peso meta', 'kg', 'weight_goal_kg', 0.1, 'Sugestão: o peso mais próximo do atual dentro da faixa saudável de IMC (18,5 a 24,9).'],
            'water' => ['Água/dia', 'ml', 'water_goal_ml', 50, 'Sugestão: 35 ml por kg de peso.'],
            'calories' => ['Calorias/dia', 'kcal', 'calorie_goal', 10, 'Sugestão: TMB × 1,2 (sem exercício), com −500 kcal para perder ou +300 kcal para ganhar peso.'],
        ],
        'Macronutrientes por dia' => [
            'protein' => ['Proteína', 'g', 'protein_goal_g', 1, 'Sugestão: 1,6 g por kg do peso meta.'],
            'carbs' => ['Carboidrato', 'g', 'carbs_goal_g', 1, 'Sugestão: o restante das calorias depois de proteínas e gorduras.'],
            'fat' => ['Gorduras', 'g', 'fat_goal_g', 1, 'Sugestão: 30% das calorias.'],
            'fiber' => ['Fibras', 'g', 'fiber_goal_g', 1, 'Sugestão: 14 g a cada 1.000 kcal (mínimo 25 g).'],
        ],
    ];
    $kcalPerGram = ['protein' => 4, 'carbs' => 4, 'fat' => 9];
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
            <p class="text-sm text-slate-500">Já vêm calculadas pelo sistema com base no seu perfil. Você pode alterar qualquer valor, por exemplo com a orientação do seu nutricionista.</p>
        </div>

        @if ($errors->any())
            <p class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first() }}</p>
        @endif

        @foreach ($fields as $group => $items)
            <div>
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $group }}</h3>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($items as $key => [$label, $unit, $name, $step, $help])
                        <div @class(['col-span-2' => $key === 'bmr'])>
                            <label class="label" for="goal-{{ $key }}">{{ $label }} <span class="font-normal text-slate-400">({{ $unit }})</span></label>
                            <input id="goal-{{ $key }}" name="{{ $name }}" type="number" step="{{ $step }}" min="0" inputmode="decimal" class="input"
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
                            <p class="mt-0.5 text-[11px] leading-snug text-slate-400">{{ $help }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

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
