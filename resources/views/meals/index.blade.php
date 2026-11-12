<x-layouts.app title="Refeições">
    <div class="space-y-4">
        {{-- Filtro de data: hoje por padrão; setas ou calendário para ver outro dia --}}
        <x-date-nav :date="$date" own-link />

        <div class="card">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-bold text-slate-800">
                    {{ $meals->count() }} {{ $meals->count() === 1 ? 'refeição' : 'refeições' }}
                </h2>
                <a href="{{ route('home', ['data' => $date->toDateString()]) }}" class="text-sm font-semibold text-navy-600">
                    {{ number_format($meals->sum('calories'), 0, ',', '.') }} kcal
                </a>
            </div>

            @if ($meals->isNotEmpty())
                <div class="space-y-1">
                    @foreach ($meals as $meal)
                        <x-meal-card :meal="$meal" compact />
                    @endforeach
                </div>
            @else
                <p class="py-8 text-center text-sm text-slate-400">
                    @if ($date->isToday())
                        Nenhuma refeição hoje. Toque no <b class="text-navy-600">+</b> para fotografar seu prato.
                    @else
                        Nenhuma refeição registrada neste dia.
                    @endif
                </p>
            @endif
        </div>
    </div>
</x-layouts.app>
