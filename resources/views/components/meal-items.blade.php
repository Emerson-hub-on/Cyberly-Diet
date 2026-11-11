@props(['items' => []])
@php
    // 12.0 → "12" ; 12.5 → "12,5"
    $g = fn ($v) => rtrim(rtrim(number_format((float) ($v ?? 0), 1, ',', '.'), '0'), ',');
@endphp

@if (! empty($items))
    <ul {{ $attributes->merge(['class' => 'divide-y divide-ice-100 rounded-xl bg-ice-50 px-3']) }}>
        @foreach ($items as $item)
            <li class="py-2">
                <p class="text-sm font-medium text-slate-800">
                    {{ $item['name'] ?? 'Item' }}
                    @if (! empty($item['portion']))
                        <span class="font-normal text-slate-400">({{ $item['portion'] }})</span>
                    @endif
                </p>
                <p class="mt-0.5 flex flex-wrap gap-x-2 gap-y-0.5 text-xs text-slate-500">
                    <span class="font-semibold text-slate-700">{{ round($item['calories'] ?? 0) }} Kcal</span>
                    <span>·</span><span><b class="font-semibold text-carbs">{{ $g($item['carbs_g'] ?? 0) }} g</b> Carb.</span>
                    <span>·</span><span><b class="font-semibold text-protein">{{ $g($item['protein_g'] ?? 0) }} g</b> Prot.</span>
                    <span>·</span><span><b class="font-semibold text-fat">{{ $g($item['fat_g'] ?? 0) }} g</b> Gordura</span>
                    <span>·</span><span><b class="font-semibold text-slate-600">{{ $g($item['sugar_g'] ?? 0) }} g</b> Açúcar</span>
                </p>
            </li>
        @endforeach
    </ul>
@endif
