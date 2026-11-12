@props(['items' => []])
@php
    // 12.0 → "12" ; 12.5 → "12,5" ; ausente → "—" (refeições antigas não têm fibra por item)
    $g = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1, ',', '.'), '0'), ',');
@endphp

@if (! empty($items))
    <div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-xl bg-ice-50 px-2']) }}>
        <table class="w-full text-xs">
            <thead>
                <tr class="text-left text-[11px] uppercase tracking-wide text-slate-500">
                    <th class="py-2 pr-1 font-semibold">Nome</th>
                    <th class="px-1 py-2 text-right font-semibold">Kcal</th>
                    <th class="px-1 py-2 text-right font-semibold text-carbs">Carb</th>
                    <th class="px-1 py-2 text-right font-semibold text-protein">Prot</th>
                    <th class="px-1 py-2 text-right font-semibold text-fat">Gord.</th>
                    <th class="px-1 py-2 text-right font-semibold text-emerald-600">Fibra</th>
                    <th class="py-2 pl-1 text-right font-semibold">Açúcar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ice-100 border-t border-ice-200">
                @foreach ($items as $item)
                    <tr class="align-top">
                        <td class="py-2 pr-1">
                            <span class="font-medium text-slate-800">{{ $item['name'] ?? 'Item' }}</span>
                            @if (! empty($item['portion']))
                                <span class="block text-[11px] text-slate-400">{{ $item['portion'] }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-1 py-2 text-right font-semibold text-slate-700">{{ round($item['calories'] ?? 0) }}</td>
                        <td class="whitespace-nowrap px-1 py-2 text-right text-slate-600">{{ $g($item['carbs_g'] ?? 0) }}</td>
                        <td class="whitespace-nowrap px-1 py-2 text-right text-slate-600">{{ $g($item['protein_g'] ?? 0) }}</td>
                        <td class="whitespace-nowrap px-1 py-2 text-right text-slate-600">{{ $g($item['fat_g'] ?? 0) }}</td>
                        <td class="whitespace-nowrap px-1 py-2 text-right text-slate-600">{{ $g($item['fiber_g'] ?? null) }}</td>
                        <td class="whitespace-nowrap py-2 pl-1 text-right text-slate-600">{{ $g($item['sugar_g'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="pb-2 text-right text-[10px] text-slate-400">Nutrientes em gramas</p>
    </div>
@endif
