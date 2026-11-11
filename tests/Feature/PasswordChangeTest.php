<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_shows_change_password_form(): void
    {
        $this->actingAs(User::factory()->create())->get('/perfil')
            ->assertOk()->assertSee('Alterar senha')->assertSee('Senha atual');
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => 'senha-antiga']);

        $this->actingAs($user)->put('/perfil/senha', [
            'current_password' => 'senha-antiga',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertRedirect('/perfil')->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('senha-nova-123', $user->fresh()->password));
        $this->assertDatabaseHas('events', ['user_id' => $user->id, 'type' => 'password.changed']);

        // Login com a nova senha funciona; com a antiga, não
        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'senha-antiga'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'senha-nova-123'])->assertRedirect('/');
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'senha-antiga']);

        $this->actingAs($user)->put('/perfil/senha', [
            'current_password' => 'errada',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertSessionHasErrorsIn('password', ['current_password']);

        $this->assertTrue(Hash::check('senha-antiga', $user->fresh()->password));
    }

    public function test_confirmation_and_length_are_validated(): void
    {
        $user = User::factory()->create(['password' => 'senha-antiga']);

        $this->actingAs($user)->put('/perfil/senha', [
            'current_password' => 'senha-antiga',
            'password' => 'curta',
            'password_confirmation' => 'outra',
        ])->assertSessionHasErrorsIn('password', ['password']);

        $this->put('/perfil/senha', [
            'current_password' => 'senha-antiga',
            'password' => 'senha-antiga',
            'password_confirmation' => 'senha-antiga',
        ])->assertSessionHasErrorsIn('password', ['password']);
    }
}
