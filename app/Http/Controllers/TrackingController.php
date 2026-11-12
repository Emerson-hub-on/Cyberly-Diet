<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\WaterLog;
use App\Models\WeightLog;
use App\Services\NutritionGoals;
use App\Services\WeightProgress;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Registros simples: peso corporal, atividade física, ingestão de água e metas.
 */
class TrackingController extends Controller
{
    public function storeWeight(Request $request)
    {
        $data = $request->validate([
            'weight_kg' => ['required', 'numeric', 'between:20,400'],
            'measured_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $date = CarbonImmutable::parse($data['measured_on'])->startOfDay();

        // Um registro por dia: salvar de novo na mesma data atualiza o peso daquele dia
        $log = $user->weightLogs()->whereBetween('measured_on', [$date, $date->endOfDay()])
            ->orderByDesc('id')->first();
        $updated = (bool) $log;

        if ($log) {
            $log->update(['weight_kg' => $data['weight_kg'], 'note' => $data['note'] ?? $log->note]);
        } else {
            $log = $user->weightLogs()->create(['measured_on' => $date] + $data);
        }

        $user->logEvent('weight.logged', $log, ['weight_kg' => $log->weight_kg, 'date' => $date->toDateString(), 'updated' => $updated]);

        $msg = sprintf('Peso de %s %s: %s kg.', $date->format('d/m'), $updated ? 'atualizado' : 'registrado', number_format($log->weight_kg, 1, ',', '.'));

        return $this->backTo('peso', $date->toDateString(), $msg);
    }

    public function destroyWeight(Request $request, WeightLog $log)
    {
        abort_unless($log->user_id === $request->user()->id, 404);
        $log->delete();

        return back()->with('status', 'Registro removido.');
    }

    public function storeActivity(Request $request)
    {
        $data = $request->validate([
            'activity' => ['required', 'string', 'max:60'],
            'duration_min' => ['required', 'integer', 'between:1,1440'],
            'calories_burned' => ['nullable', 'integer', 'between:0,20000'],
            'performed_on' => ['required', 'date'],
        ]);
        $user = $request->user();
        $data['activity'] = trim($data['activity']);
        $data['met'] = WeightProgress::metFor($data['activity']);

        // Sem kcal informada: calcula por MET × peso × horas, com o peso mais recente até a data
        $weight = WeightProgress::weightOn($user, CarbonImmutable::parse($data['performed_on']))
            ?? WeightProgress::FALLBACK_WEIGHT_KG;
        $data['calories_burned'] ??= WeightProgress::activityCalories($data['met'], (int) $data['duration_min'], $weight);

        $log = $user->activityLogs()->create($data);
        $user->logEvent('activity.logged', $log, $data + ['weight_kg_used' => $weight]);

        return $this->backTo('atividade', $data['performed_on'], "{$log->activity}: {$log->duration_min} min, −{$log->calories_burned} kcal.");
    }

    public function destroyActivity(Request $request, ActivityLog $log)
    {
        abort_unless($log->user_id === $request->user()->id, 404);
        $log->delete();

        return back()->with('status', 'Atividade removida.');
    }

    public function storeWater(Request $request)
    {
        $data = $request->validate([
            'amount_ml' => ['required', 'integer', 'between:10,5000'],
            'date' => ['required', 'date'],
        ]);

        $date = CarbonImmutable::parse($data['date']);
        $loggedAt = $date->isToday() ? now() : $date->setTime(12, 0);

        $log = $request->user()->waterLogs()->create(['amount_ml' => $data['amount_ml'], 'logged_at' => $loggedAt]);
        $request->user()->logEvent('water.logged', $log, ['amount_ml' => $log->amount_ml]);

        return $this->backTo('agua', $date->toDateString(), "+{$log->amount_ml} ml de água.");
    }

    public function destroyWater(Request $request, WaterLog $log)
    {
        abort_unless($log->user_id === $request->user()->id, 404);
        $log->delete();

        return back()->with('status', 'Registro removido.');
    }

    /**
     * Salva as metas. Campos listados em "auto" voltam ao cálculo automático (coluna NULL).
     */
    public function updateGoals(Request $request)
    {
        $data = $request->validate([
            'bmr_kcal' => ['nullable', 'integer', 'between:500,5000'],
            'weight_goal_kg' => ['nullable', 'numeric', 'between:20,400'],
            'water_goal_ml' => ['nullable', 'integer', 'between:250,10000'],
            'calorie_goal' => ['nullable', 'integer', 'between:500,10000'],
            'protein_goal_g' => ['nullable', 'integer', 'between:0,600'],
            'carbs_goal_g' => ['nullable', 'integer', 'between:0,1500'],
            'fat_goal_g' => ['nullable', 'integer', 'between:0,600'],
            'fiber_goal_g' => ['nullable', 'integer', 'between:0,200'],
            'auto' => ['nullable', 'string', 'max:200'],
        ]);

        $auto = array_filter(explode(',', $data['auto'] ?? ''));
        unset($data['auto']);
        foreach (NutritionGoals::FIELDS as $field => $column) {
            if (in_array($field, $auto, true)) {
                $data[$column] = null;
            }
        }

        $user = $request->user();
        $user->update($data);
        $user->logEvent('goals.updated', null, array_filter($data, fn ($v) => $v !== null));

        return redirect()->route('home', ['tab' => 'metas'])->with('status', 'Metas atualizadas.');
    }

    private function backTo(string $tab, string $date, string $status)
    {
        return redirect()->route('home', ['tab' => $tab, 'data' => CarbonImmutable::parse($date)->toDateString()])
            ->with('status', $status);
    }
}
