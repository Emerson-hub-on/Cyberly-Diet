<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', ['canRegister' => $this->registrationOpen()]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, remember: true)) {
            return back()->withErrors(['email' => 'E-mail ou senha incorretos.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function showRegister()
    {
        abort_unless($this->registrationOpen(), 403, 'Cadastro desativado.');

        return view('auth.register');
    }

    public function register(Request $request)
    {
        abort_unless($this->registrationOpen(), 403, 'Cadastro desativado.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($data);
        $user->logEvent('user.registered');

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** O primeiro usuário sempre pode se cadastrar; depois, só se ALLOW_REGISTRATION=true. */
    private function registrationOpen(): bool
    {
        return config('diet.allow_registration') || ! User::query()->exists();
    }
}
