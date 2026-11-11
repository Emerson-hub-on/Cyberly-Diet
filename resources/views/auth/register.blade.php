<x-layouts.guest title="Criar conta">
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <h2 class="text-lg font-bold text-slate-800">Criar conta</h2>
        @if ($errors->any())
            <p class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first() }}</p>
        @endif
        <div>
            <label class="label" for="name">Nome</label>
            <input id="name" name="name" class="input" value="{{ old('name') }}" required autofocus>
        </div>
        <div>
            <label class="label" for="email">E-mail</label>
            <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" required autocomplete="email">
        </div>
        <div>
            <label class="label" for="password">Senha (mín. 8 caracteres)</label>
            <input id="password" name="password" type="password" class="input" required autocomplete="new-password">
        </div>
        <div>
            <label class="label" for="password_confirmation">Confirmar senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password">
        </div>
        <button class="btn-primary w-full py-3">Criar conta</button>
        <p class="text-center text-sm text-slate-500">Já tem conta? <a href="{{ route('login') }}" class="font-semibold text-navy-600">Entrar</a></p>
    </form>
</x-layouts.guest>
