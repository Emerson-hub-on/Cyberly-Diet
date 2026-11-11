<x-layouts.guest title="Entrar">
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <h2 class="text-lg font-bold text-slate-800">Entrar</h2>
        @error('email')
            <p class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $message }}</p>
        @enderror
        <div>
            <label class="label" for="email">E-mail</label>
            <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" required autofocus autocomplete="email">
        </div>
        <div>
            <label class="label" for="password">Senha</label>
            <input id="password" name="password" type="password" class="input" required autocomplete="current-password">
        </div>
        <button class="btn-primary w-full py-3">Entrar</button>
        @if ($canRegister)
            <p class="text-center text-sm text-slate-500">Primeiro acesso? <a href="{{ route('register') }}" class="font-semibold text-navy-600">Criar conta</a></p>
        @endif
    </form>
</x-layouts.guest>
