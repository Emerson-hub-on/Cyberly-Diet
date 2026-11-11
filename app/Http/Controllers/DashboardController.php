<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    private const TABS = ['calorias', 'peso', 'atividade', 'agua'];

    public function index(Request $request)
    {
        $user = $request->user();
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
        $goal = $user->calorie_goal ?: config('diet.defaults.calorie_goal');
        $goalPct = $goal > 0 ? round($totals['calories'] / $goal * 100, 1) : 0;

        $weekMeals = $user->meals()->whereBetween('eaten_at', [$weekStart, $dayEnd])->get(['eaten_at', 'calories']);
        $caloriesChart = $this->dailySeries($weekStart, $date, $weekMeals, fn ($m) => $m->eaten_at, 'calories');

        // ---------- Peso corporal ----------
        $weights = $user->weightLogs()->where('measured_on', '<=', $dayEnd)
            ->orderByDesc('measured_on')->orderByDesc('id')->limit(30)->get()->reverse()->values();
        $latestWeight = $weights->last();
        $bmi = ($latestWeight && $user->height_cm)
            ? round($latestWeight->weight_kg / (($user->height_cm / 100) ** 2), 1)
            : null;
        $weightChart = [
            'labels' => $weights->map(fn ($w) => $w->measured_on->format('d/m'))->all(),
            'values' => $weights->map(fn ($w) => $w->weight_kg)->all(),
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
        $waterGoal = $user->water_goal_ml ?: config('diet.defaults.water_goal_ml');
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
            'goalPct' => $goalPct,
            'latestWeight' => $latestWeight,
            'weights' => $weights,
            'bmi' => $bmi,
            'activities' => $activities,
            'waterLogs' => $waterLogs,
            'waterTotal' => $waterTotal,
            'waterGoal' => $waterGoal,
            'charts' => [
                'calories' => $caloriesChart + ['goal' => $goal],
                'weight' => $weightChart + ['goal' => $user->weight_goal_kg],
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
