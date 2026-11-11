<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
}
