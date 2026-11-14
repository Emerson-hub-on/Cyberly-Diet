<?php

namespace Tests\Feature;

use App\Models\Meal;
use App\Models\User;
use App\Services\Cheer;
use App\Services\MealAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheerTest extends TestCase
{
    use RefreshDatabase;

    private function meal(array $totals): Meal
    {
        $m = new Meal(['calories' => $totals['calories'], 'sugar_g' => $totals['sugar'], 'fiber_g' => $totals['fiber']]);

        return $m->fill(Meal::macroSplit($totals['protein'], $totals['carbs'], $totals['fat']));
    }

    public function test_meal_kind_by_nutrients(): void
    {
        // Frango grelhado com salada: muita proteína, pouco do resto
        $this->assertSame('protein', Cheer::mealKind($this->meal(['calories' => 400, 'protein' => 45, 'carbs' => 20, 'fat' => 10, 'sugar' => 3, 'fiber' => 3])));
        // Salada com grão-de-bico: muita fibra, pouca gordura e açúcar
        $this->assertSame('fiber', Cheer::mealKind($this->meal(['calories' => 350, 'protein' => 15, 'carbs' => 45, 'fat' => 8, 'sugar' => 5, 'fiber' => 10])));
        // Pizza: nada se destaca
        $this->assertSame('meal', Cheer::mealKind($this->meal(['calories' => 800, 'protein' => 30, 'carbs' => 90, 'fat' => 35, 'sugar' => 8, 'fiber' => 4])));
        // Proteína alta mas muito açúcar (shake adoçado): mensagem geral
        $this->assertSame('meal', Cheer::mealKind($this->meal(['calories' => 300, 'protein' => 30, 'carbs' => 25, 'fat' => 5, 'sugar' => 20, 'fiber' => 1])));
    }

    public function test_every_kind_has_variations(): void
    {
        foreach (Cheer::MESSAGES as $kind => $texts) {
            $this->assertGreaterThanOrEqual(3, count($texts), $kind);
            $this->assertContains(Cheer::make($kind)['text'], $texts);
        }
        $this->assertContains('Parabéns! Se alimentar é muito importante.', Cheer::MESSAGES['meal']);
    }

    public function test_meal_upload_returns_cheer_and_layout_has_toast(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->mock(MealAnalyzer::class)->shouldReceive('analyze')->andReturn([
            'is_food' => true, 'title' => 'Frango e salada', 'items' => [], 'confidence' => 'alta', 'notes' => '', 'model' => 'x',
            'totals' => ['calories' => 400, 'protein_g' => 45, 'carbs_g' => 20, 'sugar_g' => 3, 'fat_g' => 10, 'fiber_g' => 3],
        ]);

        $this->actingAs($user)->postJson('/refeicoes', [
            'photo' => UploadedFile::fake()->image('prato.jpg'), 'meal_type' => 'almoco',
        ])->assertOk()->assertJsonPath('cheer.kind', 'protein')->assertJsonPath('cheer.icon', '💪');

        $this->get('/')->assertOk()->assertSee('x-data="cheerToast(', false);
    }

    public function test_water_cheer_depends_on_regularity(): void
    {
        $this->travelTo(now()->setTime(15, 0));
        $user = User::factory()->create();

        $this->actingAs($user)->post('/agua', ['amount_ml' => 250, 'date' => now()->toDateString()])
            ->assertSessionHas('cheer', fn ($c) => $c['kind'] === 'water');

        // Água às 9h, 11h, 13h e agora (15h): constante
        foreach ([9, 11, 13] as $h) {
            $user->waterLogs()->create(['amount_ml' => 250, 'logged_at' => now()->setTime($h, 0)]);
        }
        $this->post('/agua', ['amount_ml' => 250, 'date' => now()->toDateString()])
            ->assertSessionHas('cheer', fn ($c) => $c['kind'] === 'water_steady');

        // Outro dia com intervalo grande (8h e 15h): não é constante
        $other = User::factory()->create();
        $other->waterLogs()->create(['amount_ml' => 250, 'logged_at' => now()->setTime(8, 0)]);
        $other->waterLogs()->create(['amount_ml' => 250, 'logged_at' => now()->setTime(8, 30)]);
        $this->actingAs($other)->post('/agua', ['amount_ml' => 250, 'date' => now()->toDateString()])
            ->assertSessionHas('cheer', fn ($c) => $c['kind'] === 'water');
    }

    public function test_activity_shows_cheer_on_next_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->followingRedirects()
            ->post('/atividades', ['activity' => 'Corrida', 'duration_min' => 30, 'performed_on' => now()->toDateString()])
            ->assertOk()->assertSee('cheerToast(JSON.parse(', false)->assertSee('kind\u0022:\u0022activity', false);
    }

    public function test_nutrient_rings_open_modal_with_table_and_foods(): void
    {
        $user = User::factory()->create(['protein_goal_g' => 120, 'calorie_goal' => 2000]);
        $user->meals()->create(['meal_type' => 'almoco', 'eaten_at' => now()->setTime(12, 0), 'title' => 'Frango e arroz',
            'calories' => 600, 'protein_g' => 45, 'carbs_g' => 60, 'fat_g' => 12, 'sugar_g' => 60, 'fiber_g' => 5]);

        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee("@click=\"nutrient = 'protein'\"", false)
            ->assertSee("@click=\"nutrient = 'fiber'\"", false)
            // Proteína: 45 g de 120 g → falta 75 g; depois os alimentos ricos
            ->assertSeeInOrder(['data-nutrient="protein"', 'Proteínas do dia', 'Almoço', 'Frango e arroz', '45 g',
                'Consumido', '45 g', 'Meta do dia', '120 g', 'Falta', '75 g', 'Alimentos ricos em proteína', 'Peito de frango grelhado'], false)
            // Açúcar: limite 10% de 2000 kcal = 50 g → passou 10 g
            ->assertSeeInOrder(['data-nutrient="sugar"', 'Limite do dia', '50 g', 'Passou do limite', '10 g', 'Onde tem muito açúcar'], false)
            ->assertSeeInOrder(['data-nutrient="fiber"', 'Falta', 'Alimentos ricos em fibras'], false);
    }
}
