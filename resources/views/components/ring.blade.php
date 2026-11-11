@props(['pct' => 0, 'color' => '#16365f', 'size' => 92, 'stroke' => 9, 'label' => null])
@php
    $r = ($size - $stroke) / 2;
    $c = 2 * M_PI * $r;
    $offset = $c * (1 - min(max($pct, 0), 100) / 100);
@endphp
<div class="flex flex-col items-center gap-2">
    <div class="relative" style="width: {{ $size }}px; height: {{ $size }}px">
        <svg width="{{ $size }}" height="{{ $size }}" class="-rotate-90">
            <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none" stroke="#e7eff7" stroke-width="{{ $stroke }}"/>
            <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none" stroke="{{ $color }}" stroke-width="{{ $stroke }}"
                    stroke-linecap="round" stroke-dasharray="{{ $c }}" stroke-dashoffset="{{ $offset }}" class="transition-all duration-700"/>
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center leading-tight">{{ $slot }}</div>
    </div>
    @if ($label)
        <span class="text-sm font-medium text-slate-600">{{ $label }}</span>
    @endif
</div>
