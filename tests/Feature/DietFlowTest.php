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

    public function test_date_navigator_has_calendar_picker_keeping_selected_day(): void
    {
        $this->actingAs(User::factory()->create())->get('/?data=2026-11-05&tab=peso')->assertOk()
            ->assertSee('aria-label="Escolher data"', false)
            ->assertSee('value="2026-11-05"', false)
            ->assertSee('window.location = link($event.target.value)', false)
            ->assertSee('05/11/2026');

        // Consumo calórico: Calorias consumidas e Histórico de calorias no mesmo carrossel, Nutrientes abaixo
        $this->get('/')->assertSeeInOrder(['x-data="carousel"', 'Calorias consumidas', 'Histórico de calorias (Kcal)', 'Deslize para o lado', 'Nutrientes do dia'], false);
        $this->get('/?tab=atividade')->assertSeeInOrder(['Atividade do dia', 'Últimos 7 dias', 'Deslize para o lado para ver os últimos 7 dias', 'Registrar atividade']);
    }

    public function test_app_is_installable_from_profile(): void
    {
        $this->actingAs(User::factory()->create())->get('/perfil')->assertOk()
            ->assertSee('Instalar aplicativo')
            ->assertSee('Adicionar à Tela de Início')
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">', false);

        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('standalone', $manifest['display']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));
        $this->assertStringContainsString('Sem conexão com internet', file_get_contents(public_path('offline.html')));
        $this->assertStringContainsString('/offline.html', file_get_contents(public_path('sw.js')));
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
            'items' => [['name' => 'Arroz', 'portion' => '150 g', 'calories' => 200, 'protein_g' => 4, 'carbs_g' => 44, 'sugar_g' => 0, 'fat_g' => 0.5, 'fiber_g' => 2.3]],
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

        $response->assertOk()->assertJsonPath('meal.calories', 600)->assertJsonPath('meal.fiber_g', 8)->assertJsonPath('meal.goal_pct', 30);

        $meal = Meal::first();
        // 40*4=160, 70*4=280, 15*9=135 → 575 kcal de macros
        $this->assertEqualsWithDelta(27.8, $meal->protein_pct, 0.05);
        $this->assertEqualsWithDelta(48.7, $meal->carbs_pct, 0.05);
        $this->assertEqualsWithDelta(23.5, $meal->fat_pct, 0.05);
        Storage::disk('local')->assertExists($meal->photo_path);

        $this->assertDatabaseHas('events', ['user_id' => $user->id, 'type' => 'meal.created', 'subject_id' => $meal->id]);

        $response->assertJsonPath('meal.items.0.name', 'Arroz');

        // Tabela por alimento: Nome | Kcal | Carb | Prot | Gord. | Fibra | Açúcar
        // Nutrientes do dia: 2ª página com açúcar (limite 10% de 2000 kcal = 50 g) e fibras (meta automática: 14 g/1000 kcal = 28 g)
        $this->get('/')->assertOk()->assertSeeInOrder(['Proteínas', 'Carbos', 'Gorduras', '3g', 'de 50g', 'Açúcar', '8g', 'de 28g', 'Fibras']);
        $this->get('/')->assertOk()->assertSee('Arroz, feijão e frango')->assertSee('600 Kcal')
            ->assertSeeInOrder(['Nome', 'Kcal', 'Carb', 'Prot', 'Gord.', 'Fibra', 'Açúcar', 'Arroz', '150 g', '200', '44', '4', '0,5', '2,3', '0']);
        $this->get('/refeicoes')->assertSeeInOrder(['Fibra', 'Arroz', '150 g', '200', '44']);
        // Indicador de fibras separado no card da refeição: 8 g = 29% de 28 g
        $this->get('/refeicoes')->assertSeeInOrder(['Açúcar', '8g', 'Fibras', '29% de 28g', 'Deslize → fibras']);
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
        $this->put('/perfil/informacoes', ['sex' => 'M', 'birth_date' => '1990-05-10', 'height_cm' => 178])->assertRedirect('/perfil');
        $this->put('/metas', ['calorie_goal' => 1800, 'water_goal_ml' => 2500])->assertRedirect('/?tab=metas');

        foreach (['peso', 'atividade', 'agua'] as $tab) {
            $this->get("/?tab={$tab}")->assertOk();
        }
        $this->get('/?tab=peso')->assertSee('82,4 kg')->assertSee('IMC 26,0');
        $this->get('/?tab=agua')->assertSee('0,50 L')
            ->assertSeeInOrder(['Ingestão de água', 'Histórico de água (ml)', 'Deslize para o lado para ver o histórico de água', 'Registros do dia', '500 ml']);
        $this->assertSame(1800, $user->fresh()->calorie_goal);
        $this->assertDatabaseCount('events', 5);
    }

    public function test_weight_projection_shows_real_deficit_of_the_day(): void
    {
        // Homem, 30 anos, 180 cm, 90 kg → TMB = 900 + 1125 − 150 + 5 = 1880; × 1,2 = 2256 kcal
        $user = User::factory()->create([
            'sex' => 'M', 'birth_date' => '1996-01-01', 'height_cm' => 180, 'weight_goal_kg' => 80,
        ]);
        $user->weightLogs()->create(['weight_kg' => 90, 'measured_on' => '2026-11-01']);
        $user->meals()->create(['meal_type' => 'almoco', 'eaten_at' => '2026-11-05 12:00', 'calories' => 1500]);
        $user->activityLogs()->create(['activity' => 'Corrida', 'duration_min' => 30, 'calories_burned' => 400, 'performed_on' => '2026-11-05']);

        // 2256 + 400 − 1500 = 1156 kcal de déficit
        $this->actingAs($user)->get('/?data=2026-11-05&tab=peso')->assertOk()
            ->assertSeeInOrder(['Balanço de 05/11/2026', 'Caloria basal', '2.256 kcal',
                '+ Gasto em atividades', '400 kcal', '− Calorias consumidas', '1.500 kcal', 'Déficit do dia', '1.156 kcal']);

        // TMB cadastrada em Metas (ex.: nutricionista): aparece como está, e o balanço usa ela
        $user->update(['bmr_kcal' => 2253]);
        $this->get('/?data=2026-11-05&tab=peso')->assertOk()
            ->assertSeeInOrder(['Caloria basal (de Metas)', '2.253 kcal',
                '+ Gasto em atividades', '400 kcal', '− Calorias consumidas', '1.500 kcal', 'Déficit do dia', '1.153 kcal'])
            ->assertDontSee('Gasto da rotina');

        // Carrossel: Peso atual → Previsão → Progressão → Evolução → Variação; abaixo, formulário e histórico
        $this->get('/?data=2026-11-05&tab=peso')->assertSeeInOrder([
            'x-ref="slides"', 'Peso atual', 'IMC', 'Previsão para atingir a meta', 'Progressão', 'Evolução do peso (kg)', 'Variação diária (kg)',
            'Card 6 de 6', 'Registrar peso', 'Histórico de peso diário',
        ], false);

        // Dia sem refeições: déficit zerado (consumo desconhecido)
        $this->get('/?data=2026-11-06&tab=peso')->assertOk()
            ->assertSeeInOrder(['Balanço de 06/11/2026', '2.253 kcal', 'Déficit do dia', '0 kcal', 'Nenhuma refeição registrada']);
    }

    public function test_meals_page_shows_only_the_selected_day(): void
    {
        $user = User::factory()->create();
        $user->meals()->create(['meal_type' => 'almoco', 'eaten_at' => now()->setTime(12, 0), 'title' => 'Prato de hoje', 'calories' => 600]);
        $user->meals()->create(['meal_type' => 'jantar', 'eaten_at' => now()->subDay()->setTime(20, 0), 'title' => 'Prato de ontem', 'calories' => 700]);

        // Padrão: só o dia de hoje, com filtro de data no topo
        $this->actingAs($user)->get('/refeicoes')->assertOk()
            ->assertSeeInOrder(['Hoje', 'aria-label="Escolher data"', '1 refeição', '600 kcal', 'Prato de hoje'], false)
            ->assertDontSee('Prato de ontem');

        // Outro dia pelo filtro
        $this->get('/refeicoes?data='.now()->subDay()->toDateString())->assertOk()
            ->assertSee('Ontem')->assertSee('Prato de ontem')->assertSee('700 kcal')->assertDontSee('Prato de hoje');

        $this->get('/refeicoes?data=2020-01-01')->assertOk()->assertSee('Nenhuma refeição registrada neste dia.');
    }

        public function test_bmi_card_shows_class_table_and_distance_to_goal(): void
    {
        $user = User::factory()->create(['sex' => 'M', 'birth_date' => '1990-01-01', 'height_cm' => 180, 'weight_goal_kg' => 95]);
        $user->weightLogs()->create(['weight_kg' => 125, 'measured_on' => now()->toDateString()]);

        // 125 ÷ 1,8² = 38,6 → Obesidade grau II; meta 95 kg = IMC 29,3 (Sobrepeso)
        $this->actingAs($user)->get('/?tab=peso')->assertOk()
            ->assertSeeInOrder(['Peso atual', 'IMC', '38,6', 'Obesidade grau II', 'Classificação',
                'Peso normal', '18,5 – 24,9', '59,9 – 80,9',
                'Sobrepeso', '🎯 meta',
                'Obesidade grau II', '113,4 – 129,5',
                'Até a meta de 95,0 kg', 'IMC da meta: <b>29,3</b> (Sobrepeso)', 'Faltam <b>30,0 kg</b>', '−9,3 pontos de IMC',
                'Próxima faixa (Obesidade grau I): faltam 11,7 kg', 'Previsão para atingir a meta'], false);
        $this->get('/?tab=peso')->assertDontSee('← você');
    }

    public function test_users_cannot_see_other_users_photos(): void
    {
        $owner = User::factory()->create();
        $meal = $owner->meals()->create(['meal_type' => 'jantar', 'eaten_at' => now(), 'photo_path' => 'x.jpg']);

        $this->actingAs(User::factory()->create())->get("/refeicoes/{$meal->id}/foto")->assertNotFound();
    }
}
