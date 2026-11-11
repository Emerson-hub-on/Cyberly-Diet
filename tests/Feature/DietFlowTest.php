<?php

namespace Tests\Feature;

use App\Models\Meal;
use App\Models\User;
use App\Services\MealAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DietFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_user_can_register_and_see_dashboard(): void
    {
        $this->post('/cadastro', [
            'name' => 'Teste',
            'email' => 'teste@example.com',
            'sex' => 'M',
            'birth_date' => '2002-12-11',
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
        ])->assertRedirect('/');

        $user = User::first();
        $this->assertSame('M', $user->sex);
        $this->assertSame('2002-12-11', $user->birth_date->toDateString());

        $this->get('/')->assertOk()->assertSee('Calorias consumidas')->assertSee('Nutrientes do dia');

        // Cadastro fecha após o primeiro usuário
        auth()->logout();
        $this->get('/cadastro')->assertForbidden();
    }

    public function test_register_requires_sex_and_birth_date(): void
    {
        $this->post('/cadastro', [
            'name' => 'Teste', 'email' => 'teste@example.com',
            'password' => 'senha-segura', 'password_confirmation' => 'senha-segura',
        ])->assertSessionHasErrors(['sex', 'birth_date']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_bottom_plus_button_is_inside_an_alpine_component(): void
    {
        // Sem x-data no <nav>, o Alpine ignora o @click e o "+" não abre o modal
        $this->actingAs(User::factory()->create())->get('/refeicoes')->assertOk()
            ->assertSeeInOrder(['<nav x-data', '$store.mealModal.show()', 'aria-label="Adicionar refeição"'], false);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_meal_upload_is_analyzed_saved_and_logged(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['calorie_goal' => 2000]);

        $this->mock(MealAnalyzer::class)->shouldReceive('analyze')->once()->andReturn([
            'is_food' => true,
            'title' => 'Arroz, feijão e frango',
            'items' => [['name' => 'Arroz', 'portion' => '150 g', 'calories' => 200, 'protein_g' => 4, 'carbs_g' => 44, 'sugar_g' => 0, 'fat_g' => 0.5]],
            'totals' => ['calories' => 600, 'protein_g' => 40, 'carbs_g' => 70, 'sugar_g' => 3, 'fat_g' => 15, 'fiber_g' => 8],
            'confidence' => 'media',
            'notes' => 'Prato equilibrado.',
            'model' => 'claude-opus-5-5',
        ]);

        $response = $this->actingAs($user)->postJson('/refeicoes', [
            'photo' => UploadedFile::fake()->image('prato.jpg', 800, 600),
            'meal_type' => 'almoco',
            'date' => now()->toDateString(),
            'time' => '12:30',
        ]);

        $response->assertOk()->assertJsonPath('meal.calories', 600)->assertJsonPath('meal.goal_pct', 30);

        $meal = Meal::first();
        // 40*4=160, 70*4=280, 15*9=135 → 575 kcal de macros
        $this->assertEqualsWithDelta(27.8, $meal->protein_pct, 0.05);
        $this->assertEqualsWithDelta(48.7, $meal->carbs_pct, 0.05);
        $this->assertEqualsWithDelta(23.5, $meal->fat_pct, 0.05);
        Storage::disk('local')->assertExists($meal->photo_path);

        $this->assertDatabaseHas('events', ['user_id' => $user->id, 'type' => 'meal.created', 'subject_id' => $meal->id]);

        $response->assertJsonPath('meal.items.0.name', 'Arroz');

        // Detalhamento por alimento: "Arroz (150 g)  200 Kcal · 44 g Carb. · 4 g Prot. · 0,5 g Gordura · 0 g Açúcar"
        $this->get('/')->assertOk()->assertSee('Arroz, feijão e frango')->assertSee('600 Kcal')
            ->assertSeeInOrder(['Arroz', '(150 g)', '200 Kcal', '44 g', 'Carb.', '4 g', 'Prot.', '0,5 g', 'Gordura', '0 g', 'Açúcar']);
        $this->get('/refeicoes')->assertSeeInOrder(['Arroz', '(150 g)', '200 Kcal', '44 g', 'Carb.']);
        $this->get("/refeicoes/{$meal->id}/foto")->assertOk();
        $this->get('/refeicoes')->assertOk()->assertSee('Arroz, feijão e frango');
        $this->get('/perfil')->assertOk()->assertSee('Refeição registrada');
    }

    public function test_non_food_photo_is_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->mock(MealAnalyzer::class)->shouldReceive('analyze')->andReturn([
            'is_food' => false, 'title' => '', 'items' => [], 'confidence' => 'alta', 'notes' => '', 'model' => 'x',
            'totals' => ['calories' => 0, 'protein_g' => 0, 'carbs_g' => 0, 'sugar_g' => 0, 'fat_g' => 0, 'fiber_g' => 0],
        ]);

        $this->actingAs($user)->postJson('/refeicoes', [
            'photo' => UploadedFile::fake()->image('gato.jpg'),
            'meal_type' => 'lanche',
        ])->assertStatus(422);

        $this->assertDatabaseCount('meals', 0);
    }

    public function test_weight_activity_water_and_goals(): void
    {
        $user = User::factory()->create();
        $today = now()->toDateString();

        $this->actingAs($user)->post('/peso', ['weight_kg' => 82.4, 'measured_on' => $today])->assertRedirect();
        $this->post('/atividades', ['activity' => 'Corrida', 'duration_min' => 30, 'calories_burned' => 300, 'performed_on' => $today])->assertRedirect();
        $this->post('/agua', ['amount_ml' => 500, 'date' => $today])->assertRedirect();
        $this->put('/metas', ['calorie_goal' => 1800, 'water_goal_ml' => 2500, 'height_cm' => 178])->assertRedirect();

        foreach (['peso', 'atividade', 'agua'] as $tab) {
            $this->get("/?tab={$tab}")->assertOk();
        }
        $this->get('/?tab=peso')->assertSee('82,4 kg')->assertSee('IMC 26,0');
        $this->get('/?tab=agua')->assertSee('0,50 L');
        $this->assertSame(1800, $user->fresh()->calorie_goal);
        $this->assertDatabaseCount('events', 4);
    }

    public function test_users_cannot_see_other_users_photos(): void
    {
        $owner = User::factory()->create();
        $meal = $owner->meals()->create(['meal_type' => 'jantar', 'eaten_at' => now(), 'photo_path' => 'x.jpg']);

        $this->actingAs(User::factory()->create())->get("/refeicoes/{$meal->id}/foto")->assertNotFound();
    }
}
