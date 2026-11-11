<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationKeyTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return [
            'name' => 'Convidado',
            'email' => 'convidado@example.com',
            'sex' => 'F',
            'birth_date' => '1995-05-20',
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
        ] + $extra;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['diet.registration_key' => 'bemvindoaocyberlydiet', 'diet.allow_registration' => false]);
        User::factory()->create(); // já existe um usuário (o administrador)
    }

    public function test_register_page_shows_key_field(): void
    {
        $this->get('/cadastro')->assertOk()->assertSee('Chave de cadastro');
    }

    public function test_cannot_register_without_key(): void
    {
        $this->post('/cadastro', $this->payload())->assertSessionHasErrors('registration_key');
        $this->assertDatabaseMissing('users', ['email' => 'convidado@example.com']);
    }

    public function test_cannot_register_with_wrong_key(): void
    {
        $this->post('/cadastro', $this->payload(['registration_key' => 'chute']))
            ->assertSessionHasErrors('registration_key')
            ->assertSessionHasInput('email', 'convidado@example.com'); // não perde o que foi digitado

        $this->assertDatabaseMissing('users', ['email' => 'convidado@example.com']);
    }

    public function test_can_register_with_correct_key(): void
    {
        $this->post('/cadastro', $this->payload(['registration_key' => ' bemvindoaocyberlydiet ']))
            ->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'convidado@example.com', 'sex' => 'F']);
        $this->assertAuthenticated();
    }

    public function test_without_key_configured_registration_closes_after_first_user(): void
    {
        config(['diet.registration_key' => null]);

        $this->get('/cadastro')->assertForbidden();
    }

    public function test_registration_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/cadastro', $this->payload(['registration_key' => "errada{$i}"]));
        }

        $this->post('/cadastro', $this->payload(['registration_key' => 'bemvindoaocyberlydiet']))->assertStatus(429);
    }
}
