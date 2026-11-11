<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WeightProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_bmr_mifflin_st_jeor(): void
    {
        // 10×123 + 6,25×180 − 5×35 + 5 = 2185
        $this->assertEqualsWithDelta(2185, WeightProgress::bmr(123, 180, 35, 'M'), 0.01);
        $this->assertEqualsWithDelta(2185 - 166, WeightProgress::bmr(123, 180, 35, 'F'), 0.01);
    }

    public function test_activity_calories_by_met(): void
    {
        $this->assertSame(5.0, WeightProgress::metFor('academia'));
        $this->assertSame(9.8, WeightProgress::metFor(' Corrida '));
        $this->assertSame(5.0, WeightProgress::metFor('Musculacao')); // sem acento
        $this->assertSame(4.0, WeightProgress::metFor('Beach tennis')); // desconhecida → padrão

        $this->assertSame(308, WeightProgress::activityCalories(5.0, 30, 123));  // Academia 30 min
        $this->assertSame(804, WeightProgress::activityCalories(9.8, 40, 123));  // Corrida 40 min
    }

    public function test_projection_to_goal(): void
    {
        $r = WeightProgress::project(123, 95, 2000, 300, 180, 35, 'M');

        $this->assertNotNull($r['days']);
        $this->assertGreaterThan(200, $r['days']);
        $this->assertLessThan(400, $r['days']);
    }

    public function test_projection_impossible_with_surplus(): void
    {
        $r = WeightProgress::project(123, 95, 4000, 0, 180, 35, 'M');

        $this->assertNull($r['days']);
        $this->assertGreaterThan(123, $r['equilibrium_kg']);
    }

    public function test_activity_without_kcal_is_calculated_from_weight(): void
    {
        $user = User::factory()->create();
        $today = now()->toDateString();

        $this->actingAs($user)->post('/peso', ['weight_kg' => 123, 'measured_on' => $today]);
        $this->post('/atividades', ['activity' => 'Academia', 'duration_min' => 30, 'performed_on' => $today])->assertRedirect();
        $this->post('/atividades', ['activity' => 'Corrida', 'duration_min' => 40, 'performed_on' => $today])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', ['activity' => 'Academia', 'calories_burned' => 308]);
        $this->assertDatabaseHas('activity_logs', ['activity' => 'Corrida', 'calories_burned' => 804]);
        $this->get('/?tab=atividade')->assertSee('−308 kcal')->assertSee('−804 kcal');
    }

    public function test_weight_tab_shows_goal_projection_and_progression(): void
    {
        $user = User::factory()->create([
            'calorie_goal' => 2000, 'weight_goal_kg' => 95, 'height_cm' => 180,
            'sex' => 'M', 'birth_date' => now()->subYears(35)->subDays(10),
        ]);
        $user->weightLogs()->create(['weight_kg' => 125, 'measured_on' => now()->subDays(14)]);
        $user->weightLogs()->create(['weight_kg' => 123, 'measured_on' => now()]);
        $user->activityLogs()->create(['activity' => 'Corrida', 'duration_min' => 40, 'calories_burned' => 804, 'performed_on' => now()]);
        $user->meals()->create(['meal_type' => 'almoco', 'eaten_at' => now(), 'calories' => 900]);

        $this->actingAs($user)->get('/?tab=peso')->assertOk()
            ->assertSee('Meta de peso')
            ->assertSee('95,0 kg')
            ->assertSee('Faltam')
            ->assertSee('28,0 kg')
            ->assertSee('Previsão para atingir a meta')
            ->assertSee('Progredindo')
            ->assertSee('Progressão');
    }

    public function test_weight_tab_asks_for_missing_profile_data(): void
    {
        $user = User::factory()->create(['weight_goal_kg' => 95]);
        $user->weightLogs()->create(['weight_kg' => 123, 'measured_on' => now()]);

        $this->actingAs($user)->get('/?tab=peso')->assertOk()
            ->assertSee('Para calcular o déficit')
            ->assertSee('sexo');
    }
}
