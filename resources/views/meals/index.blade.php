<x-layouts.app title="Refeições">
    <div class="space-y-4">
        @forelse ($meals->groupBy(fn ($m) => $m->eaten_at->toDateString()) as $day => $dayMeals)
            @php($d = \Carbon\CarbonImmutable::parse($day))
            <div class="card">
                <a href="{{ route('home', ['data' => $day]) }}" class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold text-slate-800">{{ $d->isToday() ? 'Hoje' : ucfirst($d->translatedFormat('l')) }} · {{ $d->format('d/m/Y') }}</h2>
                    <span class="text-sm font-semibold text-navy-600">{{ number_format($dayMeals->sum('calories'), 0, ',', '.') }} kcal</span>
                </a>
                <div class="space-y-3">
                    @foreach ($dayMeals as $meal)
                        <div class="flex gap-3">
                            @if ($meal->photo_path)
                                <img src="{{ route('meals.photo', $meal) }}" alt="" loading="lazy" class="size-14 shrink-0 rounded-xl object-cover">
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold uppercase tracking-wide text-navy-600">{{ $meal->type_label }} <span class="font-normal text-slate-400">{{ $meal->eaten_at->format('H:i') }}</span></p>
                                <p class="truncate text-sm font-medium text-slate-800">{{ $meal->title }}</p>
                                <p class="text-xs text-slate-500">{{ $meal->calories }} Kcal · {{ $meal->carbs_g }} g Carb. · {{ $meal->protein_g }} g Prot. · {{ $meal->fat_g }} g Gord. · {{ $meal->sugar_g }} g Açúcar</p>
                            </div>
                        </div>
                        <x-meal-items :items="$meal->items" />

                    @endforeach
                </div>
            </div>
        @empty
            <div class="card py-10 text-center text-sm text-slate-400">
                Nenhuma refeição ainda. Toque no <b class="text-navy-600">+</b> para fotografar seu primeiro prato.
            </div>
        @endforelse

        {{ $meals->links() }}
    </div>
</x-layouts.app>
