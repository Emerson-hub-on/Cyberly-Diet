<?php

namespace App\Services;

use App\Models\User;
use App\Models\WeightLog;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Cálculos de balanço energético e progressão de peso.
 *
 * - Metabolismo basal (TMB): Mifflin-St Jeor.
 * - Gasto diário sem exercício (GET) = TMB × 1,2 (sedentário). Os exercícios são somados à parte,
 *   a partir das atividades registradas, para não contar duas vezes.
 * - Atividade: kcal = MET × peso (kg) × horas.
 * - 1 kg de gordura ≈ 7.700 kcal.
 */
class WeightProgress
{
    public const SEDENTARY_FACTOR = 1.2;

    public const FALLBACK_WEIGHT_KG = 70;

    // ---------------------------------------------------------------- fórmulas

    public static function bmr(float $weightKg, int $heightCm, int $age, string $sex): float
    {
        $base = 10 * $weightKg + 6.25 * $heightCm - 5 * $age;

        return $sex === 'F' ? $base - 161 : $base + 5;
    }

    /** Gasto diário sem contar exercícios (TMB × 1,2). */
    public static function baseExpenditure(float $weightKg, int $heightCm, int $age, string $sex): float
    {
        return self::bmr($weightKg, $heightCm, $age, $sex) * self::SEDENTARY_FACTOR;
    }

    public static function metFor(string $activity): float
    {
        $needle = Str::of($activity)->lower()->ascii()->trim()->value();

        foreach (config('diet.activities') as $name => $met) {
            if (Str::of($name)->lower()->ascii()->value() === $needle) {
                return (float) $met;
            }
        }

        return (float) config('diet.default_met');
    }

    public static function activityCalories(float $met, int $minutes, float $weightKg): int
    {
        return (int) round($met * $weightKg * ($minutes / 60));
    }

    /** Peso mais recente registrado até a data (ou null). */
    public static function weightOn(User $user, CarbonImmutable $date): ?float
    {
        return $user->weightLogs()->where('measured_on', '<=', $date->endOfDay())
            ->orderByDesc('measured_on')->orderByDesc('id')->value('weight_kg');
    }

    /**
     * Simula, dia a dia, quanto tempo leva para ir do peso atual até a meta mantendo
     * a ingestão diária e a média de atividades. O gasto basal é recalculado com o novo peso.
     *
     * @return array{days: ?int, equilibrium_kg: ?float}
     */
    public static function project(float $weight, float $goal, float $intakePerDay, float $activityPerDay, int $heightCm, int $age, string $sex): array
    {
        $direction = $goal < $weight ? -1 : 1; // -1 = perder, 1 = ganhar
        $kcalPerKg = config('diet.kcal_per_kg');

        // Peso em que o gasto se iguala à ingestão (ponto onde o peso estabiliza)
        $sexConst = $sex === 'F' ? -161 : 5;
        $equilibrium = (($intakePerDay - $activityPerDay) / self::SEDENTARY_FACTOR - 6.25 * $heightCm + 5 * $age - $sexConst) / 10;

        $w = $weight;
        for ($day = 1; $day <= 3650; $day++) {
            $balance = $intakePerDay - self::baseExpenditure($w, $heightCm, $age, $sex) - $activityPerDay;

            // Balanço no sentido errado (ex.: quer perder mas está em superávit)
            if ($balance * $direction <= 0) {
                return ['days' => null, 'equilibrium_kg' => round($equilibrium, 1)];
            }

            $w += $balance / $kcalPerKg;

            if (($goal - $w) * $direction <= 0) {
                return ['days' => $day, 'equilibrium_kg' => round($equilibrium, 1)];
            }
        }

        return ['days' => null, 'equilibrium_kg' => round($equilibrium, 1)];
    }

    // ---------------------------------------------------------------- resumo para a tela

    public function forUser(User $user, CarbonImmutable $date): array
    {
        $end = $date->endOfDay();

        $weights = $user->weightLogs()->where('measured_on', '<=', $end)
            ->orderBy('measured_on')->orderBy('id')->get();

        $current = $weights->last();
        $start = $weights->first();
        $goal = $user->weight_goal_kg;

        $missing = array_keys(array_filter([
            'peso atual' => ! $current,
            'peso meta' => ! $goal,
            'altura' => ! $user->height_cm,
            'sexo' => ! $user->sex,
            'data de nascimento' => ! $user->birth_date,
        ]));

        $result = [
            'missing' => $missing,
            'current' => $current?->weight_kg,
            'start' => $start?->weight_kg,
            'start_date' => $start?->measured_on,
            'goal' => $goal,
            'trend' => $this->trend($weights),
        ];

        if (! $current || ! $goal) {
            return $result;
        }

        $toGo = round($goal - $current->weight_kg, 1);
        $totalPlanned = $start ? $goal - $start->weight_kg : 0;
        $result['to_go_kg'] = $toGo;
        $result['progress_pct'] = ($totalPlanned != 0 && $start)
            ? (int) round(max(0, min(100, ($current->weight_kg - $start->weight_kg) / $totalPlanned * 100)))
            : 0;
        $result['reached'] = abs($toGo) < 0.1 || ($totalPlanned != 0 && ($goal - $current->weight_kg) * $totalPlanned <= 0);

        if ($missing) {
            return $result;
        }

        $age = $user->age();
        $height = $user->height_cm;
        $sex = $user->sex;
        $intakeGoal = $user->calorie_goal ?: config('diet.defaults.calorie_goal');

        $daily = $this->dailyBalances($user, $date->subDays(55), $date, $weights, $height, $age, $sex);
        $last7 = $daily->slice(-7);
        $avgActivity = round($last7->avg('activity'), 0);
        $loggedDays = $last7->where('logged', true);

        $base = self::baseExpenditure($current->weight_kg, $height, $age, $sex);
        $plannedDeficit = round($base + $avgActivity - $intakeGoal);
        $planned = self::project($current->weight_kg, $goal, $intakeGoal, $avgActivity, $height, $age, $sex);

        $real = null;
        if ($loggedDays->count() >= 3) {
            $avgIntake = $loggedDays->avg('intake');
            $realActivity = $loggedDays->avg('activity');
            $real = [
                'days_logged' => $loggedDays->count(),
                'avg_intake' => (int) round($avgIntake),
                'deficit' => (int) round($base + $realActivity - $avgIntake),
            ] + self::project($current->weight_kg, $goal, $avgIntake, $realActivity, $height, $age, $sex);
        }

        // Mudança esperada pelo balanço calórico x mudança real, desde a primeira pesagem
        $sinceStart = $start
            ? $this->dailyBalances($user, CarbonImmutable::parse($start->measured_on), $date, $weights, $height, $age, $sex)
            : collect();
        $expectedChange = $sinceStart->where('logged', true)->sum('balance') / config('diet.kcal_per_kg');

        return $result + [
            'bmr' => (int) round(self::bmr($current->weight_kg, $height, $age, $sex)),
            'base_expenditure' => (int) round($base),
            'intake_goal' => $intakeGoal,
            'avg_activity' => (int) $avgActivity,
            'planned_deficit' => (int) $plannedDeficit,
            'planned' => $planned + ['date' => $planned['days'] ? $date->addDays($planned['days']) : null],
            'real' => $real ? $real + ['date' => $real['days'] ? $date->addDays($real['days']) : null] : null,
            'expected_change_kg' => round($expectedChange, 1),
            'actual_change_kg' => round($current->weight_kg - $start->weight_kg, 1),
            'logged_days_since_start' => $sinceStart->where('logged', true)->count(),
            'weeks' => $this->weeks($daily, $weights, $date),
        ];
    }

    /**
     * Balanço de cada dia: consumo (refeições) − gasto basal − atividades.
     * "logged" = houve refeições registradas no dia (dias sem registro não entram no déficit real).
     */
    private function dailyBalances(User $user, CarbonImmutable $from, CarbonImmutable $to, Collection $weights, int $height, int $age, string $sex): Collection
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();

        $intake = $user->meals()->whereBetween('eaten_at', [$from, $to])->get(['eaten_at', 'calories'])
            ->groupBy(fn ($m) => $m->eaten_at->toDateString())->map->sum('calories');
        $activity = $user->activityLogs()->whereBetween('performed_on', [$from, $to])->get(['performed_on', 'calories_burned'])
            ->groupBy(fn ($a) => $a->performed_on->toDateString())->map->sum('calories_burned');

        return collect(CarbonPeriod::create($from, $to->startOfDay()))->map(function ($day) use ($intake, $activity, $weights, $height, $age, $sex) {
            $key = $day->toDateString();
            $weight = $weights->filter(fn (WeightLog $w) => $w->measured_on->toDateString() <= $key)->last()?->weight_kg
                ?? $weights->first()?->weight_kg ?? self::FALLBACK_WEIGHT_KG;
            $base = self::baseExpenditure($weight, $height, $age, $sex);
            $in = (int) ($intake[$key] ?? 0);
            $act = (int) ($activity[$key] ?? 0);

            return [
                'date' => $key,
                'logged' => $in > 0,
                'intake' => $in,
                'activity' => $act,
                'expenditure' => $base + $act,
                'balance' => $in - $base - $act, // negativo = déficit
            ];
        })->values();
    }

    /** Resumo das últimas 4 semanas (terminando na data selecionada). */
    private function weeks(Collection $daily, Collection $weights, CarbonImmutable $date): array
    {
        $weeks = [];
        $kcalPerKg = config('diet.kcal_per_kg');

        for ($i = 3; $i >= 0; $i--) {
            $wEnd = $date->subDays($i * 7);
            $wStart = $wEnd->subDays(6);
            $days = $daily->filter(fn ($d) => $d['date'] >= $wStart->toDateString() && $d['date'] <= $wEnd->toDateString());
            $logged = $days->where('logged', true);

            $weightEnd = $weights->filter(fn ($w) => $w->measured_on->toDateString() <= $wEnd->toDateString())->last()?->weight_kg;
            $weightStart = $weights->filter(fn ($w) => $w->measured_on->toDateString() < $wStart->toDateString())->last()?->weight_kg;

            $deficit = -$logged->sum('balance');

            $weeks[] = [
                'label' => $wStart->format('d/m').' – '.$wEnd->format('d/m'),
                'days_logged' => $logged->count(),
                'avg_intake' => $logged->isNotEmpty() ? (int) round($logged->avg('intake')) : null,
                'activity' => (int) $days->sum('activity'),
                'deficit' => (int) round($deficit),
                'expected_kg' => round(-$deficit / $kcalPerKg, 2),
                'weight' => $weightEnd,
                'change_kg' => ($weightEnd !== null && $weightStart !== null) ? round($weightEnd - $weightStart, 1) : null,
            ];
        }

        return $weeks;
    }

    /** Tendência pelas pesagens: compara a última com a de ~7 dias antes (ou a anterior). */
    private function trend(Collection $weights): ?array
    {
        if ($weights->count() < 2) {
            return null;
        }

        $last = $weights->last();
        $ref = $weights->filter(fn ($w) => $w->measured_on->lte($last->measured_on->copy()->subDays(7)))->last()
            ?? $weights[$weights->count() - 2];
        $diff = round($last->weight_kg - $ref->weight_kg, 1);
        $days = max(1, $ref->measured_on->diffInDays($last->measured_on));

        return [
            'diff_kg' => $diff,
            'days' => (int) $days,
            'status' => match (true) {
                $diff <= -0.2 => 'perdendo',
                $diff >= 0.2 => 'ganhando',
                default => 'estavel',
            },
        ];
    }
}
