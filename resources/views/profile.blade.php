@php
    $eventLabels = [
        'meal.created' => ['🍽️', 'Refeição registrada'],
        'meal.deleted' => ['🗑️', 'Refeição removida'],
        'weight.logged' => ['⚖️', 'Peso registrado'],
        'activity.logged' => ['🏃', 'Atividade registrada'],
        'water.logged' => ['💧', 'Água registrada'],
        'goals.updated' => ['🎯', 'Metas atualizadas'],
        'user.registered' => ['✨', 'Conta criada'],
    ];
@endphp

<x-layouts.app title="Perfil">
    <div class="space-y-4">
        <div class="card flex items-center gap-4">
            <div class="flex size-12 items-center justify-center rounded-full bg-navy-700 text-lg font-bold text-white">
                {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-slate-800">{{ $user->name }}</p>
                <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn-ghost px-3 py-2 text-sm">Sair</button>
            </form>
        </div>

        <form method="POST" action="{{ route('goals.update') }}" class="card space-y-3">
            @csrf @method('PUT')
            <h2 class="text-lg font-bold text-slate-800">Metas</h2>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="calorie_goal">Calorias/dia (kcal)</label>
                    <input id="calorie_goal" name="calorie_goal" type="number" min="500" max="10000" class="input" value="{{ old('calorie_goal', $user->calorie_goal) }}" required>
                </div>
                <div>
                    <label class="label" for="water_goal_ml">Água/dia (ml)</label>
                    <input id="water_goal_ml" name="water_goal_ml" type="number" min="250" max="10000" step="50" class="input" value="{{ old('water_goal_ml', $user->water_goal_ml) }}" required>
                </div>
                <div>
                    <label class="label" for="weight_goal_kg">Peso meta (kg)</label>
                    <input id="weight_goal_kg" name="weight_goal_kg" type="number" step="0.1" min="20" max="400" class="input" value="{{ old('weight_goal_kg', $user->weight_goal_kg) }}">
                </div>
                <div>
                    <label class="label" for="height_cm">Altura (cm)</label>
                    <input id="height_cm" name="height_cm" type="number" min="50" max="250" class="input" value="{{ old('height_cm', $user->height_cm) }}">
                </div>
                <div>
                    <label class="label" for="sex">Sexo</label>
                    <select id="sex" name="sex" class="input">
                        <option value="">—</option>
                        <option value="M" @selected(old('sex', $user->sex) === 'M')>Masculino</option>
                        <option value="F" @selected(old('sex', $user->sex) === 'F')>Feminino</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="birth_date">Nascimento</label>
                    <input id="birth_date" name="birth_date" type="date" class="input" value="{{ old('birth_date', $user->birth_date?->toDateString()) }}">
                </div>
            </div>
            <p class="text-xs text-slate-400">Altura, sexo e idade são usados para calcular seu gasto calórico diário (metabolismo basal) e a previsão de quando você atinge o peso meta.</p>
            <button class="btn-primary w-full">Salvar metas</button>
        </form>

        <div id="eventos" class="card">
            <h2 class="mb-3 text-lg font-bold text-slate-800">Registro de eventos</h2>
            @forelse ($events as $event)
                @php([$icon, $label] = $eventLabels[$event->type] ?? ['•', $event->type])
                @php($p = $event->payload ?? [])
                <div class="flex gap-3 border-t border-ice-100 py-2.5 text-sm first:border-t-0">
                    <span class="text-lg leading-none">{{ $icon }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-slate-700">{{ $label }}</p>
                        <p class="text-xs text-slate-500">
                            @switch($event->type)
                                @case('meal.created')
                                    {{ config('diet.meal_types')[$p['meal_type'] ?? ''] ?? '' }} · {{ $p['calories'] ?? 0 }} kcal
                                    (P {{ $p['protein_pct'] ?? 0 }}% · C {{ $p['carbs_pct'] ?? 0 }}% · G {{ $p['fat_pct'] ?? 0 }}%)
                                    · dia: {{ $p['day_calories'] ?? 0 }} kcal = {{ $p['day_goal_pct'] ?? 0 }}% da meta
                                    @break
                                @case('weight.logged') {{ $p['weight_kg'] ?? '' }} kg @break
                                @case('activity.logged') {{ $p['activity'] ?? '' }} · {{ $p['duration_min'] ?? 0 }} min · {{ $p['calories_burned'] ?? 0 }} kcal @break
                                @case('water.logged') {{ $p['amount_ml'] ?? 0 }} ml @break
                                @case('meal.deleted') {{ $p['calories'] ?? 0 }} kcal @break
                            @endswitch
                        </p>
                    </div>
                    <span class="shrink-0 text-xs text-slate-400">{{ $event->created_at->format('d/m H:i') }}</span>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-slate-400">Nenhum evento ainda.</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
