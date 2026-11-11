<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return view('profile', [
            'user' => $user,
            'events' => $user->events()->latest('created_at')->latest('id')->limit(50)->get(),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ], [
            'current_password.required' => 'Informe a senha atual.',
            'current_password.current_password' => 'A senha atual está incorreta.',
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmação não confere com a nova senha.',
            'password.different' => 'A nova senha deve ser diferente da atual.',
            'password.min' => 'A nova senha deve ter pelo menos 8 caracteres.',
        ]);

        $user = $request->user();
        $user->update(['password' => $data['password']]); // o cast "hashed" criptografa
        $user->logEvent('password.changed');

        // Nova sessão + novo "lembrar de mim": desconecta logins antigos que usavam o token anterior
        $user->setRememberToken(Str::random(60));
        $user->save();
        $request->session()->regenerate();

        return redirect()->route('profile')->with('status', 'Senha alterada com sucesso.');
    }
}
