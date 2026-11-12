@php
    $eventLabels = [
        'meal.created' => ['🍽️', 'Refeição registrada'],
        'meal.deleted' => ['🗑️', 'Refeição removida'],
        'weight.logged' => ['⚖️', 'Peso registrado'],
        'activity.logged' => ['🏃', 'Atividade registrada'],
        'water.logged' => ['💧', 'Água registrada'],
        'goals.updated' => ['🎯', 'Metas atualizadas'],
        'password.changed' => ['🔒', 'Senha alterada'],
        'profile.updated' => ['👤', 'Informações pessoais atualizadas'],
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

        {{-- Instalar aplicativo (PWA) --}}
        <div x-data="installApp" id="instalar" class="card">
            <div class="flex items-center gap-3">
                <img src="/icons/icon-192.png" alt="" class="size-12 shrink-0 rounded-xl">
                <div class="min-w-0 flex-1">
                    <h2 class="font-bold text-slate-800">Aplicativo no celular</h2>
                    <p class="text-sm text-slate-500" x-text="installed ? 'Instalado neste aparelho ✓' : 'Adicione o ícone na tela inicial.'">Adicione o ícone na tela inicial.</p>
                </div>
            </div>

            <button type="button" x-show="!installed" @click="install()" class="btn-primary mt-4 w-full">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Instalar aplicativo
            </button>

            {{-- Passo a passo quando o navegador não instala sozinho (sempre no iPhone) --}}
            <div x-show="help && !installed" x-cloak x-transition class="mt-4 rounded-xl bg-ice-50 p-4 text-sm text-slate-700">
                <template x-if="platform === 'ios'">
                    <ol class="list-decimal space-y-2 pl-5">
                        <li>Abra este site no <b>Safari</b>.</li>
                        <li>Toque em <b>Compartilhar</b>
                            <svg class="inline size-5 align-text-bottom text-navy-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25H15m0-3-3-3m0 0-3 3m3-3V15"/></svg>
                            na barra inferior.</li>
                        <li>Role e toque em <b>Adicionar à Tela de Início</b>.</li>
                        <li>Toque em <b>Adicionar</b>. O ícone do Cyberly Diet aparece na tela do iPhone.</li>
                    </ol>
                </template>
                <template x-if="platform === 'android'">
                    <ol class="list-decimal space-y-2 pl-5">
                        <li>Abra este site no <b>Chrome</b>.</li>
                        <li>Toque no menu <b>⋮</b> (canto superior direito).</li>
                        <li>Toque em <b>Instalar app</b> ou <b>Adicionar à tela inicial</b>.</li>
                        <li>Confirme em <b>Instalar</b>.</li>
                    </ol>
                </template>
                <template x-if="platform === 'desktop'">
                    <p>No Chrome ou Edge, clique no ícone de instalar <b>⊕</b> na barra de endereço, ou no menu <b>⋮</b> → <b>Instalar Cyberly Diet</b>.</p>
                </template>
            </div>
        </div>

        {{-- Informações pessoais (usadas no cálculo da TMB e das metas automáticas) --}}
        @php($infoErrors = $errors->getBag('info'))
        <div x-data="{ editing: {{ $infoErrors->any() ? 'true' : 'false' }} }" id="informacoes" class="card">
            <h2 class="text-lg font-bold text-slate-800">Informações pessoais</h2>

            <dl x-show="!editing" class="mt-3 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-ice-50 px-2 py-3">
                    <dt class="text-xs text-slate-500">Sexo</dt>
                    <dd class="mt-0.5 font-semibold text-slate-800">{{ ['M' => 'Masculino', 'F' => 'Feminino'][$user->sex] ?? '—' }}</dd>
                </div>
                <div class="rounded-xl bg-ice-50 px-2 py-3">
                    <dt class="text-xs text-slate-500">Nascimento</dt>
                    <dd class="mt-0.5 font-semibold text-slate-800">{{ $user->birth_date?->format('d/m/Y') ?? '—' }}</dd>
                    @if ($user->age() !== null)
                        <dd class="text-[11px] text-slate-400">{{ $user->age() }} anos</dd>
                    @endif
                </div>
                <div class="rounded-xl bg-ice-50 px-2 py-3">
                    <dt class="text-xs text-slate-500">Altura</dt>
                    <dd class="mt-0.5 font-semibold text-slate-800">{{ $user->height_cm ? $user->height_cm . ' cm' : '—' }}</dd>
                </div>
            </dl>

            <form x-show="editing" x-cloak x-transition method="POST" action="{{ route('profile.update') }}" class="mt-3 space-y-3">
                @csrf @method('PUT')
                @if ($infoErrors->any())
                    <p class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $infoErrors->first() }}</p>
                @endif
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="sex">Sexo</label>
                        <select id="sex" name="sex" class="input" required>
                            <option value="">—</option>
                            <option value="M" @selected(old('sex', $user->sex) === 'M')>Masculino</option>
                            <option value="F" @selected(old('sex', $user->sex) === 'F')>Feminino</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="height_cm">Altura (cm)</label>
                        <input id="height_cm" name="height_cm" type="number" min="50" max="250" inputmode="numeric" class="input" value="{{ old('height_cm', $user->height_cm) }}">
                    </div>
                    <div class="col-span-2">
                        <label class="label" for="birth_date">Nascimento</label>
                        <input id="birth_date" name="birth_date" type="date" class="input" required value="{{ old('birth_date', $user->birth_date?->toDateString()) }}">
                    </div>
                </div>
                <button class="btn-primary w-full">Salvar informações</button>
                <button type="button" class="w-full text-center text-sm font-semibold text-slate-500" @click="editing = false">Cancelar</button>
            </form>

            <button x-show="!editing" type="button" @click="editing = true" class="btn-ghost mt-3 w-full">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                Editar informações
            </button>
            <p class="mt-3 text-xs text-slate-400">
                Sexo, idade, altura e peso são usados para calcular a caloria basal e as
                <a href="{{ route('home', ['tab' => 'metas']) }}" class="font-semibold text-navy-600 underline">metas</a> automáticas.
            </p>
        </div>

        {{-- Alterar senha --}}
        @php($pwErrors = $errors->getBag('password'))
        <div x-data="{ open: {{ $pwErrors->any() ? 'true' : 'false' }}, show: false }" id="senha" class="card">
            <button type="button" @click="open = !open" class="flex w-full items-center justify-between">
                <h2 class="text-lg font-bold text-slate-800">Alterar senha</h2>
                <span class="text-sm font-semibold text-navy-600" x-text="open ? 'Fechar ▲' : 'Alterar ▼'"></span>
            </button>

            <form x-show="open" x-cloak x-transition method="POST" action="{{ route('password.update') }}" class="mt-4 space-y-3">
                @csrf @method('PUT')

                @if ($pwErrors->any())
                    <p class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $pwErrors->first() }}</p>
                @endif

                <div>
                    <label class="label" for="current_password">Senha atual</label>
                    <input id="current_password" name="current_password" :type="show ? 'text' : 'password'" class="input" required autocomplete="current-password">
                </div>
                <div>
                    <label class="label" for="new_password">Nova senha <span class="font-normal text-slate-400">(mín. 8 caracteres)</span></label>
                    <input id="new_password" name="password" :type="show ? 'text' : 'password'" minlength="8" class="input" required autocomplete="new-password">
                </div>
                <div>
                    <label class="label" for="password_confirmation">Confirmar nova senha</label>
                    <input id="password_confirmation" name="password_confirmation" :type="show ? 'text' : 'password'" minlength="8" class="input" required autocomplete="new-password">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" x-model="show" class="size-4 rounded accent-navy-700"> Mostrar senhas
                </label>
                <button class="btn-primary w-full">Salvar nova senha</button>
            </form>
        </div>

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
