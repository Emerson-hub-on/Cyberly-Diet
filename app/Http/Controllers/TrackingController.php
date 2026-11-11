<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\WaterLog;
use App\Models\WeightLog;
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

        $log = $request->user()->weightLogs()->create($data);
        $request->user()->logEvent('weight.logged', $log, ['weight_kg' => $log->weight_kg]);

        return $this->backTo('peso', $data['measured_on'], 'Peso registrado.');
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
        $data['calories_burned'] ??= 0;

        $log = $request->user()->activityLogs()->create($data);
        $request->user()->logEvent('activity.logged', $log, $data);

        return $this->backTo('atividade', $data['performed_on'], 'Atividade registrada.');
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

    public function updateGoals(Request $request)
    {
        $data = $request->validate([
            'calorie_goal' => ['required', 'integer', 'between:500,10000'],
            'water_goal_ml' => ['required', 'integer', 'between:250,10000'],
            'weight_goal_kg' => ['nullable', 'numeric', 'between:20,400'],
            'height_cm' => ['nullable', 'integer', 'between:50,250'],
        ]);

        $request->user()->update($data);
        $request->user()->logEvent('goals.updated', null, $data);

        return back()->with('status', 'Metas atualizadas.');
    }

    private function backTo(string $tab, string $date, string $status)
    {
        return redirect()->route('home', ['tab' => $tab, 'data' => CarbonImmutable::parse($date)->toDateString()])
            ->with('status', $status);
    }
}
