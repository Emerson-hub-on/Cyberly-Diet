<?php

namespace App\Http\Controllers;

use App\Services\NutritionGoals;
use App\Services\WeightProgress;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    private const TABS = ['calorias', 'peso', 'atividade', 'agua', 'metas'];

    public function index(Request $request, WeightProgress $progress)
    {
        $user = $request->user();
        $nutrition = NutritionGoals::for($user);
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'calorias';

        try {
            $date = CarbonImmutable::parse($request->query('data', 'today'))->startOfDay();
        } catch (\Throwable) {
            $date = CarbonImmutable::today();
        }

        $dayStart = $date->startOfDay();
        $dayEnd = $date->endOfDay();
        $weekStart = $date->subDays(6)->startOfDay();

        // ---------- Consumo calórico ----------
        $meals = $user->meals()->whereBetween('eaten_at', [$dayStart, $dayEnd])->orderBy('eaten_at')->get();

        $totals = [
            'calories' => (int) $meals->sum('calories'),
            'protein_g' => round($meals->sum('protein_g'), 1),
            'carbs_g' => round($meals->sum('carbs_g'), 1),
            'sugar_g' => round($meals->sum('sugar_g'), 1),
            'fat_g' => round($meals->sum('fat_g'), 1),
            'fiber_g' => round($meals->sum('fiber_g'), 1),
        ];
        $split = \App\Models\Meal::macroSplit($totals['protein_g'], $totals['carbs_g'], $totals['fat_g']);
        $goal = $nutrition['values']['calories'];
        $goalPct = $goal > 0 ? round($totals['calories'] / $goal * 100, 1) : 0;

        $weekMeals = $user->meals()->whereBetween('eaten_at', [$weekStart, $dayEnd])->get(['eaten_at', 'calories']);
        $caloriesChart = $this->dailySeries($weekStart, $date, $weekMeals, fn ($m) => $m->eaten_at, 'calories');

        // ---------- Peso corporal ----------
        // Um peso por dia (se houver mais de um registro no dia, vale o último), até 60 dias com registro
        $weights = $user->weightLogs()->where('measured_on', '<=', $dayEnd)
            ->orderByDesc('measured_on')->orderByDesc('id')->limit(300)->get()
            ->unique(fn ($w) => $w->measured_on->toDateString())
            ->take(60)->reverse()->values();
        $latestWeight = $weights->last()
            ?? $user->weightLogs()->orderByDesc('measured_on')->orderByDesc('id')->first();
        $bmi = ($latestWeight && $user->height_cm)
            ? round($latestWeight->weight_kg / (($user->height_cm / 100) ** 2), 1)
            : null;

        // Histórico diário com a variação em relação ao registro anterior
        $weightHistory = $weights->map(function ($w, $i) use ($weights) {
            $prev = $weights[$i - 1] ?? null;

            return [
                'log' => $w,
                'diff' => $prev ? round($w->weight_kg - $prev->weight_kg, 1) : null,
                'days' => $prev ? (int) $prev->measured_on->diffInDays($w->measured_on) : null,
            ];
        });
        $weightChart = [
            'labels' => $weights->map(fn ($w) => $w->measured_on->format('d/m'))->all(),
            'values' => $weights->map(fn ($w) => $w->weight_kg)->all(),
            'diffs' => $weightHistory->map(fn ($h) => $h['diff'] ?? 0)->all(),
        ];

        // ---------- Atividade física ----------
        $activities = $user->activityLogs()->whereDate('performed_on', $date->toDateString())->orderBy('id')->get();
        $weekActivities = $user->activityLogs()
            ->whereBetween('performed_on', [$weekStart, $dayEnd])->get();
        $activityChart = $this->dailySeries($weekStart, $date, $weekActivities, fn ($a) => $a->performed_on, 'duration_min');
        $activityKcalChart = $this->dailySeries($weekStart, $date, $weekActivities, fn ($a) => $a->performed_on, 'calories_burned');

        // ---------- Água ----------
        $waterLogs = $user->waterLogs()->whereBetween('logged_at', [$dayStart, $dayEnd])->orderBy('logged_at')->get();
        $waterTotal = (int) $waterLogs->sum('amount_ml');
        $waterGoal = $nutrition['values']['water'];
        $weekWater = $user->waterLogs()->whereBetween('logged_at', [$weekStart, $dayEnd])->get(['logged_at', 'amount_ml']);
        $waterChart = $this->dailySeries($weekStart, $date, $weekWater, fn ($w) => $w->logged_at, 'amount_ml');

        return view('dashboard', [
            'tab' => $tab,
            'date' => $date,
            'isToday' => $date->isToday(),
            'meals' => $meals,
            'mealsByType' => $meals->groupBy('meal_type'),
            'totals' => $totals,
            'split' => $split,
            'goal' => $goal,
            'nutrition' => $nutrition,
            'goalPct' => $goalPct,
            'latestWeight' => $latestWeight,
            'weights' => $weights,
            'weightHistory' => $weightHistory,
            'bmi' => $bmi,
            'progress' => $progress->forUser($user, $date),
            'activityMets' => config('diet.activities'),
            'defaultMet' => config('diet.default_met'),
            'weightForActivity' => $latestWeight?->weight_kg ?? WeightProgress::FALLBACK_WEIGHT_KG,
            'activities' => $activities,
            'waterLogs' => $waterLogs,
            'waterTotal' => $waterTotal,
            'waterGoal' => $waterGoal,
            'charts' => [
                'calories' => $caloriesChart + ['goal' => $goal],
                'weight' => $weightChart + ['goal' => $nutrition['values']['weight']],
                'activity' => $activityChart + ['kcal' => $activityKcalChart['values']],
                'water' => $waterChart + ['goal' => $waterGoal],
            ],
        ]);
    }

    /**
     * Soma um campo por dia no intervalo (feito em PHP para funcionar igual em SQLite e Postgres).
     *
     * @return array{labels: list<string>, values: list<float|int>}
     */
    private function dailySeries(CarbonImmutable $from, CarbonImmutable $to, Collection $rows, callable $dateOf, string $field): array
    {
        $byDay = $rows->groupBy(fn ($row) => $dateOf($row)->toDateString())
            ->map(fn (Collection $group) => $group->sum($field));

        $labels = [];
        $values = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $labels[] = $day->format('d/m');
            $values[] = round((float) ($byDay[$day->toDateString()] ?? 0), 1);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
