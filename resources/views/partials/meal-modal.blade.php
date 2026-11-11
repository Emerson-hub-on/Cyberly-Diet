{{-- Modal "Nova refeição": foto do prato + tipo de refeição → análise por IA --}}
<div x-data x-cloak x-show="$store.mealModal.open" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
     @keydown.escape.window="$store.mealModal.hide()">
    <div x-show="$store.mealModal.open" x-transition.opacity class="absolute inset-0 bg-navy-950/60 backdrop-blur-sm"
         @click="$store.mealModal.hide()"></div>

    <div x-show="$store.mealModal.open"
         x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0"
         x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0"
         x-data="mealForm('{{ request('data', now()->toDateString()) }}')"
         class="relative max-h-[92dvh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-white p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-2xl sm:rounded-3xl">

        <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-ice-200 sm:hidden"></div>

        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-navy-800" x-text="result ? 'Refeição registrada' : 'Nova refeição'"></h2>
            <button type="button" class="rounded-full p-1.5 text-slate-400 hover:bg-ice-100" @click="$store.mealModal.hide()" aria-label="Fechar">
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Formulário --}}
        <form x-show="!result" @submit.prevent="submit" class="space-y-4">
            {{-- Foto --}}
            <div>
                <template x-if="preview">
                    <div class="relative overflow-hidden rounded-2xl">
                        <img :src="preview" alt="Prévia do prato" class="h-56 w-full object-cover">
                        <button type="button" @click="file = null; preview = null"
                                class="absolute right-2 top-2 rounded-full bg-navy-950/60 px-3 py-1 text-xs font-medium text-white">Trocar</button>
                    </div>
                </template>
                <div x-show="!preview" class="grid grid-cols-2 gap-3">
                    <label class="flex h-36 cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-navy-100 bg-navy-50 text-navy-700 transition hover:border-navy-500">
                        <svg class="size-9" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/></svg>
                        <span class="text-sm font-semibold">Tirar foto</span>
                        <input type="file" accept="image/*" capture="environment" class="hidden" @change="pick">
                    </label>
                    <label class="flex h-36 cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-ice-200 bg-ice-50 text-slate-600 transition hover:border-navy-500">
                        <svg class="size-9" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
                        <span class="text-sm font-semibold">Galeria / upload</span>
                        <input type="file" accept="image/*" class="hidden" @change="pick">
                    </label>
                </div>
            </div>

            {{-- Tipo de refeição --}}
            <div>
                <span class="label">Tipo de refeição</span>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (config('diet.meal_types') as $value => $label)
                        <button type="button" @click="mealType = '{{ $value }}'"
                                :class="mealType === '{{ $value }}' ? 'border-navy-600 bg-navy-700 text-white' : 'border-ice-200 bg-white text-slate-700'"
                                class="rounded-xl border px-3 py-2.5 text-sm font-semibold transition">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="meal-date">Data</label>
                    <input id="meal-date" type="date" x-model="date" class="input" required>
                </div>
                <div>
                    <label class="label" for="meal-time">Hora</label>
                    <input id="meal-time" type="time" x-model="time" class="input" required>
                </div>
            </div>

            <div>
                <label class="label" for="meal-notes">Observação <span class="font-normal text-slate-400">(opcional)</span></label>
                <textarea id="meal-notes" x-model="notes" rows="2" maxlength="500" class="input resize-none"
                          placeholder="Ex.: frango grelhado sem óleo, 2 colheres de arroz"></textarea>
            </div>

            <p x-show="error" x-text="error" class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700"></p>

            <button type="submit" class="btn-primary w-full py-3" :disabled="loading">
                <svg x-show="loading" class="size-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".25" stroke-width="4"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>
                <span x-text="loading ? 'Analisando seu prato com IA…' : 'Analisar e salvar'"></span>
            </button>
            <p x-show="loading" class="text-center text-xs text-slate-400">Isso pode levar alguns segundos.</p>
        </form>

        {{-- Resultado --}}
        <template x-if="result">
            <div class="space-y-4">
                <img x-show="preview" :src="preview" alt="" class="h-40 w-full rounded-2xl object-cover">
                <div>
                    <p class="font-semibold text-slate-700" x-text="result.title"></p>
                    <p class="mt-1 text-4xl font-light text-navy-800"><span x-text="result.calories"></span> <span class="text-xl">Kcal</span></p>
                    <p class="text-sm text-slate-500"><span x-text="result.goal_pct"></span>% da sua meta diária</p>
                </div>
                <div class="grid grid-cols-4 gap-2 text-center text-xs">
                    <div class="rounded-xl bg-blue-50 p-2"><p class="text-base font-bold text-protein" x-text="result.protein_g + 'g'"></p>Proteínas<p class="text-slate-400" x-text="result.protein_pct + '%'"></p></div>
                    <div class="rounded-xl bg-cyan-50 p-2"><p class="text-base font-bold text-carbs" x-text="result.carbs_g + 'g'"></p>Carbos<p class="text-slate-400" x-text="result.carbs_pct + '%'"></p></div>
                    <div class="rounded-xl bg-indigo-50 p-2"><p class="text-base font-bold text-fat" x-text="result.fat_g + 'g'"></p>Gorduras<p class="text-slate-400" x-text="result.fat_pct + '%'"></p></div>
                    <div class="rounded-xl bg-slate-50 p-2"><p class="text-base font-bold text-slate-500" x-text="result.sugar_g + 'g'"></p>Açúcar</div>
                </div>
                <ul x-show="result.items && result.items.length" class="divide-y divide-ice-100 rounded-xl bg-ice-50 px-3">
                    <template x-for="item in result.items">
                        <li class="py-2">
                            <p class="text-sm font-medium text-slate-800"><span x-text="item.name"></span> <span class="font-normal text-slate-400" x-text="'(' + item.portion + ')'"></span></p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                <b class="text-slate-700" x-text="Math.round(item.calories) + ' Kcal'"></b>
                                · <b class="text-carbs" x-text="fmt(item.carbs_g) + ' g'"></b> Carb.
                                · <b class="text-protein" x-text="fmt(item.protein_g) + ' g'"></b> Prot.
                                · <b class="text-fat" x-text="fmt(item.fat_g) + ' g'"></b> Gordura
                                · <b class="text-slate-600" x-text="fmt(item.sugar_g) + ' g'"></b> Açúcar
                            </p>
                        </li>
                    </template>
                </ul>
                <p class="text-xs text-slate-400">Estimativa feita por IA a partir da foto (confiança: <span x-text="result.confidence"></span>). Os valores são aproximados.</p>
                <button type="button" class="btn-primary w-full py-3" @click="finish">Ver no painel</button>
            </div>
        </template>
    </div>
</div>
