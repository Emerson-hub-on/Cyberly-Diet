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

    /** Informações pessoais (sexo, nascimento, altura): usadas no cálculo da TMB e das metas automáticas. */
    public function updateInfo(Request $request)
    {
        $data = $request->validateWithBag('info', [
            'sex' => ['required', 'in:M,F'],
            'birth_date' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'height_cm' => ['nullable', 'integer', 'between:50,250'],
        ], [
            'sex.required' => 'Informe o sexo.',
            'birth_date.required' => 'Informe a data de nascimento.',
            'birth_date.before' => 'A data de nascimento deve ser anterior a hoje.',
            'height_cm.between' => 'A altura deve estar entre 50 e 250 cm.',
        ]);

        $user = $request->user();
        $user->update($data);
        $user->logEvent('profile.updated', null, $data);

        return redirect()->route('profile')->with('status', 'Informações atualizadas.');
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
