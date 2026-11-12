<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use App\Services\MealAnalyzer;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class MealController extends Controller
{
    public function index(Request $request)
    {
        $meals = $request->user()->meals()->orderByDesc('eaten_at')->paginate(20);

        return view('meals.index', ['meals' => $meals]);
    }

    public function store(Request $request, MealAnalyzer $analyzer)
    {
        @set_time_limit(180);

        $data = $request->validate([
            'photo' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:8192'],
            'meal_type' => ['required', Rule::in(array_keys(config('diet.meal_types')))],
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'photo.required' => 'Envie a foto do prato.',
            'photo.mimes' => 'Formato de imagem não suportado (use JPG, PNG ou WEBP).',
            'photo.max' => 'A imagem deve ter no máximo 8 MB.',
            'meal_type.required' => 'Escolha o tipo de refeição.',
        ]);

        $user = $request->user();
        $file = $request->file('photo');
        $bytes = $file->get();
        $mime = $file->getMimeType() === 'image/jpg' ? 'image/jpeg' : $file->getMimeType();
        $mealLabel = config('diet.meal_types')[$data['meal_type']];

        try {
            $analysis = $analyzer->analyze($bytes, $mime, $mealLabel, $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (! ($analysis['is_food'] ?? false)) {
            return response()->json(['message' => 'Não identifiquei comida nesta foto. Tente outra imagem do prato.'], 422);
        }

        $eatenAt = CarbonImmutable::parse(($data['date'] ?? now()->toDateString()).' '.($data['time'] ?? now()->format('H:i')));

        // Guarda a foto (local em dev; Supabase Storage via S3 em produção)
        $disk = config('diet.photos_disk');
        $ext = $file->guessExtension() ?: 'jpg';
        $path = "meals/{$user->id}/".$eatenAt->format('Y/m').'/'.Str::uuid().".{$ext}";
        Storage::disk($disk)->put($path, $bytes);

        $t = $analysis['totals'];
        $protein = round((float) $t['protein_g'], 1);
        $carbs = round((float) $t['carbs_g'], 1);
        $fat = round((float) $t['fat_g'], 1);
        $calories = (int) round((float) $t['calories']);
        $goal = $user->calorie_goal ?: config('diet.defaults.calorie_goal');

        $meal = $user->meals()->create([
            'meal_type' => $data['meal_type'],
            'eaten_at' => $eatenAt,
            'photo_path' => $path,
            'title' => Str::limit($analysis['title'] ?? $mealLabel, 250, ''),
            'items' => $analysis['items'] ?? [],
            'calories' => $calories,
            'protein_g' => $protein,
            'carbs_g' => $carbs,
            'sugar_g' => round((float) $t['sugar_g'], 1),
            'fat_g' => $fat,
            'fiber_g' => round((float) ($t['fiber_g'] ?? 0), 1),
            ...Meal::macroSplit($protein, $carbs, $fat),
            'goal_pct' => $goal > 0 ? round($calories / $goal * 100, 1) : 0,
            'confidence' => $analysis['confidence'] ?? null,
            'notes' => $data['notes'] ?? null,
            'ai_notes' => $analysis['notes'] ?? null,
            'ai_model' => $analysis['model'] ?? null,
        ]);

        // Total do dia após esta refeição, para o registro do evento
        $dayCalories = (int) $user->meals()
            ->whereBetween('eaten_at', [$eatenAt->startOfDay(), $eatenAt->endOfDay()])
            ->sum('calories');

        $user->logEvent('meal.created', $meal, [
            'meal_type' => $meal->meal_type,
            'calories' => $meal->calories,
            'protein_g' => $meal->protein_g,
            'carbs_g' => $meal->carbs_g,
            'sugar_g' => $meal->sugar_g,
            'fat_g' => $meal->fat_g,
            'protein_pct' => $meal->protein_pct,
            'carbs_pct' => $meal->carbs_pct,
            'fat_pct' => $meal->fat_pct,
            'goal_pct' => $meal->goal_pct,
            'day_calories' => $dayCalories,
            'day_goal_pct' => $goal > 0 ? round($dayCalories / $goal * 100, 1) : 0,
            'ai_model' => $meal->ai_model,
        ]);

        return response()->json([
            'message' => 'Refeição registrada!',
            'meal' => $meal->only(['id', 'title', 'items', 'calories', 'protein_g', 'carbs_g', 'sugar_g', 'fat_g', 'fiber_g', 'protein_pct', 'carbs_pct', 'fat_pct', 'goal_pct', 'confidence']),
            'redirect' => route('home', ['tab' => 'calorias', 'data' => $eatenAt->toDateString()]),
        ]);
    }

    public function photo(Request $request, Meal $meal)
    {
        abort_unless($meal->user_id === $request->user()->id && $meal->photo_path, 404);

        $disk = Storage::disk(config('diet.photos_disk'));
        abort_unless($disk->exists($meal->photo_path), 404);

        return $disk->response($meal->photo_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function destroy(Request $request, Meal $meal)
    {
        abort_unless($meal->user_id === $request->user()->id, 404);

        if ($meal->photo_path) {
            Storage::disk(config('diet.photos_disk'))->delete($meal->photo_path);
        }

        $request->user()->logEvent('meal.deleted', null, ['meal_id' => $meal->id, 'calories' => $meal->calories]);
        $meal->delete();

        return back()->with('status', 'Refeição removida.');
    }
}
