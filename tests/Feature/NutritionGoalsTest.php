<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NutritionGoals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NutritionGoalsTest extends TestCase
{
    use RefreshDatabase;

    private function person(array $attrs = []): User
    {
        // Homem, 30 anos, 180 cm
        $user = User::factory()->create($attrs + ['sex' => 'M', 'birth_date' => now()->subYears(30)->subMonth(), 'height_cm' => 180]);
        $user->weightLogs()->create(['weight_kg' => 100, 'measured_on' => now()->toDateString()]);

        return $user;
    }

    public function test_goals_are_suggested_from_sex_age_height_and_weight(): void
    {
        $g = NutritionGoals::compute($this->person(), 100.0);

        // Caloria basal = TMB (10·100 + 6,25·180 − 5·30 + 5 = 1980) × 1,2 = 2376
        $this->assertSame(2376, $g['values']['bmr']);
        // Peso meta: IMC 24,9 em 1,80 m = 80,7 → 80,5 kg
        $this->assertSame(80.5, $g['values']['weight']);
        $this->assertSame(3500, $g['values']['water']);          // 35 ml × 100 kg
        $this->assertSame(1880, $g['values']['calories']);       // 2376 − 500 = 1876 → 1880
        $this->assertSame(129, $g['values']['protein']);         // 1,6 × 80,5 = 128,8
        $this->assertSame(63, $g['values']['fat']);              // 30% de 1880 / 9
        $this->assertSame(199, $g['values']['carbs']);           // (1880 − 129·4 − 63·9) / 4 = 199,25
        $this->assertSame(26, $g['values']['fiber']);            // 14 g × 1,88
        $this->assertTrue(collect($g['auto'])->every(fn ($a) => $a));

        // Mulher com os mesmos dados: TMB 1814 × 1,2
        $this->assertSame(2177, NutritionGoals::compute($this->person(['sex' => 'F']), 100.0)['values']['bmr']);
    }

    public function test_custom_values_replace_suggestions_and_chain_to_the_rest(): void
    {
        // Nutricionista passou caloria basal 2200 e 140 g de proteína: usada como está, sem acréscimo
        $g = NutritionGoals::compute($this->person(['bmr_kcal' => 2200, 'protein_goal_g' => 140]), 100.0);

        $this->assertSame(2200, $g['values']['bmr']);
        $this->assertFalse($g['auto']['bmr']);
        $this->assertSame(1700, $g['values']['calories']);       // 2200 − 500 (automático, segue a caloria basal)
        $this->assertTrue($g['auto']['calories']);
        $this->assertSame(140, $g['values']['protein']);
        $this->assertSame(157, $g['values']['carbs']);           // (1700 − 140·4 − 57·9) / 4 = 156,75
    }

    public function test_without_profile_data_uses_safe_defaults(): void
    {
        $g = NutritionGoals::compute(User::factory()->create(), null);

        $this->assertNull($g['values']['bmr']);
        $this->assertNull($g['values']['weight']);
        $this->assertSame(2000, $g['values']['calories']);
        $this->assertSame(2000, $g['values']['water']);
        $this->assertSame(28, $g['values']['fiber']);
        $this->assertSame(['peso', 'altura', 'sexo', 'data de nascimento'], NutritionGoals::missing($g['inputs']));
    }

    public function test_goals_tab_saves_custom_values_and_auto_fields(): void
    {
        $user = $this->person(['calorie_goal' => 1700]);

        $this->actingAs($user)->get('/?tab=metas')->assertOk()
            ->assertSeeInOrder(['Caloria basal', 'Calorias/dia', 'Peso meta', 'Água/dia', 'Proteína', 'Carboidrato', 'Gorduras', 'Fibras', 'Salvar metas'])
            ->assertSee('aria-label="O que é Caloria basal?"', false)
            ->assertSee('Como o sistema calcula')
            ->assertSee('Mifflin-St Jeor');

        // Usuário define TMB e fibras; calorias voltam para o automático
        $this->put('/metas', [
            'bmr_kcal' => 2100, 'weight_goal_kg' => 85, 'water_goal_ml' => 3000, 'calorie_goal' => 1700,
            'protein_goal_g' => 150, 'carbs_goal_g' => 200, 'fat_goal_g' => 60, 'fiber_goal_g' => 35,
            'auto' => 'calories,carbs,fat',
        ])->assertRedirect('/?tab=metas');

        $user->refresh();
        $this->assertSame(2100, $user->bmr_kcal);
        $this->assertSame(85.0, $user->weight_goal_kg);
        $this->assertSame(150, $user->protein_goal_g);
        $this->assertSame(35, $user->fiber_goal_g);
        $this->assertNull($user->calorie_goal);
        $this->assertNull($user->carbs_goal_g);

        // Nutrientes do dia usam as metas: proteína 150 g, fibras 35 g
        $this->get('/')->assertSeeInOrder(['Nutrientes do dia', 'de 150g', 'Proteínas', 'de 35g', 'Fibras']);
    }

    public function test_profile_shows_personal_info_with_edit_button(): void
    {
        $user = $this->person();

        $this->actingAs($user)->get('/perfil')->assertOk()
            ->assertSee('Informações pessoais')->assertSee('Masculino')->assertSee('180 cm')->assertSee('Editar informações')
            ->assertDontSee('Calorias/dia (kcal)');

        $this->put('/perfil/informacoes', ['sex' => 'F', 'birth_date' => '1995-03-20', 'height_cm' => 165])->assertRedirect('/perfil');
        $this->assertSame(165, $user->fresh()->height_cm);
        $this->assertSame('F', $user->fresh()->sex);

        $this->put('/perfil/informacoes', ['sex' => 'X', 'birth_date' => '', 'height_cm' => 10])
            ->assertSessionHasErrorsIn('info', ['sex', 'birth_date', 'height_cm']);
    }
}
