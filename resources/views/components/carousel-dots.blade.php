{{-- Pontinhos de um carrossel x-data="carousel" + dica de deslizar no primeiro card --}}
@props(['labels' => [], 'hint' => null])
<div class="mt-3 flex items-center justify-center gap-1">
    @foreach ($labels as $i => $label)
        <button type="button" class="p-1" aria-label="{{ $label }}" @click="go({{ $i }})">
            <span class="block h-2 rounded-full transition-all" :class="page === {{ $i }} ? 'w-5 bg-navy-600' : 'w-2 bg-ice-200'"></span>
        </button>
    @endforeach
</div>
@if ($hint)
    <p x-show="page === 0" class="mt-1 text-center text-[11px] text-slate-400">{{ $hint }}</p>
@endif
