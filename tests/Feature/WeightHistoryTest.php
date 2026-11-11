<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_twice_on_same_day_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $today = now()->toDateString();

        $this->actingAs($user)->post('/peso', ['weight_kg' => 123.0, 'measured_on' => $today])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'registrado'));
        $this->post('/peso', ['weight_kg' => 122.6, 'measured_on' => $today])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'atualizado'));

        $this->assertSame(1, $user->weightLogs()->count());
        $this->assertSame(122.6, $user->weightLogs()->first()->weight_kg);
        $this->assertDatabaseCount('events', 2);
    }

    public function test_form_is_prefilled_with_last_saved_weight(): void
    {
        $user = User::factory()->create();
        $user->weightLogs()->create(['weight_kg' => 121.4, 'measured_on' => now()->subDays(3)]);

        $this->actingAs($user)->get('/?tab=peso')->assertOk()
            ->assertSee('value="121.4"', false)
            ->assertSee('Último registro:')
            ->assertSee('121,4 kg');
    }

    public function test_daily_history_shows_variation_and_charts(): void
    {
        $user = User::factory()->create(['weight_goal_kg' => 95]);
        $user->weightLogs()->create(['weight_kg' => 123.0, 'measured_on' => now()->subDays(2)]);
        $user->weightLogs()->create(['weight_kg' => 122.5, 'measured_on' => now()->subDay()]);
        $user->weightLogs()->create(['weight_kg' => 122.8, 'measured_on' => now()]);

        $this->actingAs($user)->get('/?tab=peso')->assertOk()
            ->assertSee('Histórico de peso diário')
            ->assertSee('Variação diária (kg)')
            ->assertSee('chart-weight-diff', false)
            // mais recente primeiro: subiu 0,3 / baixou 0,5 / início
            ->assertSeeInOrder(['▲ +0,3', '122,8 kg', '▼ -0,5', '122,5 kg', 'início', '123,0 kg'])
            ->assertSee('Atualizar peso do dia');
    }

    public function test_duplicate_logs_of_same_day_count_once_in_history(): void
    {
        $user = User::factory()->create();
        $user->weightLogs()->create(['weight_kg' => 120.0, 'measured_on' => now()]);
        $user->weightLogs()->create(['weight_kg' => 119.5, 'measured_on' => now()]); // registro antigo duplicado

        $response = $this->actingAs($user)->get('/?tab=peso')->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), now()->format('d/m/Y').'</p>'));
        $response->assertSee('119,5 kg');
    }
}
