<x-layouts.app title="Refeições">
    <div class="space-y-4">
        @forelse ($meals->groupBy(fn ($m) => $m->eaten_at->toDateString()) as $day => $dayMeals)
            @php($d = \Carbon\CarbonImmutable::parse($day))
            <div class="card">
                <a href="{{ route('home', ['data' => $day]) }}" class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold text-slate-800">{{ $d->isToday() ? 'Hoje' : ucfirst($d->translatedFormat('l')) }} · {{ $d->format('d/m/Y') }}</h2>
                    <span class="text-sm font-semibold text-navy-600">{{ number_format($dayMeals->sum('calories'), 0, ',', '.') }} kcal</span>
                </a>
                <div class="space-y-1">
                    @foreach ($dayMeals as $meal)
                        <x-meal-card :meal="$meal" compact />
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
