<?php

namespace App\Services;

use App\Models\User;

/**
 * Metas nutricionais do usuário.
 *
 * Cada meta tem uma sugestão calculada pelo sistema (a partir de sexo, idade, altura e peso)
 * e pode ser substituída pelo usuário (ex.: valores passados pelo nutricionista).
 * Coluna NULL = automático. As sugestões são encadeadas: TMB → calorias → macronutrientes,
 * então mudar a TMB ou o peso meta recalcula o que estiver no automático.
 *
 * As mesmas fórmulas existem em resources/js/app.js (goalsForm), para o recálculo ao vivo na tela.
 *
 * - TMB: Mifflin-St Jeor.
 * - Calorias/dia: TMB × 1,2 (sem exercício); −500 kcal para perder peso, +300 para ganhar.
 *   Nunca abaixo de 1.200 kcal (mulheres) / 1.500 kcal (homens).
 * - Água: 35 ml por kg.
 * - Proteína: 1,6 g por kg do peso meta (até 35% das calorias).
 * - Gorduras: 30% das calorias. Carboidratos: o restante.
 * - Fibras: 14 g a cada 1.000 kcal (mínimo 25 g).
 */
class NutritionGoals
{
    public const FIELDS = [
        'bmr' => 'bmr_kcal',
        'weight' => 'weight_goal_kg',
        'water' => 'water_goal_ml',
        'calories' => 'calorie_goal',
        'protein' => 'protein_goal_g',
        'carbs' => 'carbs_goal_g',
        'fat' => 'fat_goal_g',
        'fiber' => 'fiber_goal_g',
    ];

    public const DEFAULT_CALORIES = 2000;

    public const DEFAULT_WATER_ML = 2000;

    /**
     * Metas em vigor (personalizada ou sugerida) e se cada uma está no automático. Memorizado por requisição.
     *
     * @return array{values: array<string, int|float|null>, auto: array<string, bool>, suggested: array<string, int|float|null>, inputs: array}
     */
    public static function for(User $user): array
    {
        $key = 'nutrition-goals.'.$user->id;
        $cache = app()->bound('request') ? request()->attributes : null;

        if ($cache?->has($key)) {
            return $cache->get($key);
        }

        $weight = $user->weightLogs()->orderByDesc('measured_on')->orderByDesc('id')->value('weight_kg');
        $goals = self::compute($user, $weight !== null ? (float) $weight : null);
        $cache?->set($key, $goals);

        return $goals;
    }

    /** @return array{values: array, auto: array, suggested: array, inputs: array} */
    public static function compute(User $user, ?float $weight): array
    {
        $inputs = [
            'weight' => $weight,
            'height' => $user->height_cm,
            'age' => $user->age(),
            'sex' => $user->sex,
        ];

        $values = $auto = $suggested = [];
        $pick = function (string $field, $suggestion) use ($user, &$values, &$auto, &$suggested) {
            $own = $user->{self::FIELDS[$field]};
            $suggested[$field] = $suggestion;
            $auto[$field] = $own === null;
            $values[$field] = $own ?? $suggestion;

            return $values[$field];
        };

        $bmr = $pick('bmr', self::suggestBmr($inputs));
        $weightGoal = $pick('weight', self::suggestWeightGoal($weight, $user->height_cm));
        $pick('water', self::suggestWater($weight));
        $calories = $pick('calories', self::suggestCalories($bmr, $weight, $weightGoal, $user->sex));
        $protein = $pick('protein', self::suggestProtein($calories, $weightGoal ?? $weight));
        $fat = $pick('fat', self::suggestFat($calories));
        $pick('carbs', self::suggestCarbs($calories, $protein, $fat));
        $pick('fiber', self::suggestFiber($calories));

        return compact('values', 'auto', 'suggested', 'inputs');
    }

    /** Dados que faltam para a TMB ser calculada. */
    public static function missing(array $inputs): array
    {
        return array_keys(array_filter([
            'peso' => $inputs['weight'] === null,
            'altura' => ! $inputs['height'],
            'sexo' => ! $inputs['sex'],
            'data de nascimento' => $inputs['age'] === null,
        ]));
    }

    // ---------------------------------------------------------------- sugestões

    public static function suggestBmr(array $inputs): ?int
    {
        if (self::missing($inputs)) {
            return null;
        }

        return (int) round(WeightProgress::bmr($inputs['weight'], $inputs['height'], $inputs['age'], $inputs['sex']));
    }

    /** Peso dentro da faixa saudável de IMC (18,5–24,9) mais próximo do atual. */
    public static function suggestWeightGoal(?float $weight, ?int $heightCm): ?float
    {
        if ($weight === null || ! $heightCm) {
            return null;
        }

        $h2 = ($heightCm / 100) ** 2;
        $target = min(max($weight, 18.5 * $h2), 24.9 * $h2);

        return round($target * 2) / 2; // de 0,5 em 0,5 kg
    }

    public static function suggestWater(?float $weight): int
    {
        return $weight === null ? self::DEFAULT_WATER_ML : (int) (round($weight * 35 / 50) * 50);
    }

    public static function suggestCalories(?int $bmr, ?float $weight, ?float $weightGoal, ?string $sex): int
    {
        if (! $bmr) {
            return self::DEFAULT_CALORIES;
        }

        $kcal = $bmr * WeightProgress::SEDENTARY_FACTOR;
        if ($weight !== null && $weightGoal !== null) {
            $kcal += match (true) {
                $weightGoal < $weight - 0.5 => -500,
                $weightGoal > $weight + 0.5 => 300,
                default => 0,
            };
        }

        $floor = $sex === 'F' ? 1200 : 1500;

        return (int) (round(max($kcal, $floor) / 10) * 10);
    }

    public static function suggestProtein(int $calories, ?float $refWeight): int
    {
        $byWeight = ($refWeight ?? WeightProgress::FALLBACK_WEIGHT_KG) * 1.6;

        return (int) round(min($byWeight, $calories * 0.35 / 4));
    }

    public static function suggestFat(int $calories): int
    {
        return (int) round($calories * 0.30 / 9);
    }

    public static function suggestCarbs(int $calories, int $protein, int $fat): int
    {
        return (int) max(0, round(($calories - $protein * 4 - $fat * 9) / 4));
    }

    public static function suggestFiber(int $calories): int
    {
        return (int) max(25, round($calories / 1000 * 14));
    }
}
