<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meal extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'eaten_at' => 'datetime',
            'items' => 'array',
            'calories' => 'integer',
            'protein_g' => 'float',
            'carbs_g' => 'float',
            'sugar_g' => 'float',
            'fat_g' => 'float',
            'fiber_g' => 'float',
            'protein_pct' => 'float',
            'carbs_pct' => 'float',
            'fat_pct' => 'float',
            'goal_pct' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return config('diet.meal_types')[$this->meal_type] ?? $this->meal_type;
    }

    /**
     * Distribuição calórica dos macronutrientes (4 kcal/g proteína e carbo, 9 kcal/g gordura).
     *
     * @return array{protein_pct: float, carbs_pct: float, fat_pct: float}
     */
    public static function macroSplit(float $protein, float $carbs, float $fat): array
    {
        $kcal = $protein * 4 + $carbs * 4 + $fat * 9;

        if ($kcal <= 0) {
            return ['protein_pct' => 0, 'carbs_pct' => 0, 'fat_pct' => 0];
        }

        return [
            'protein_pct' => round($protein * 4 / $kcal * 100, 1),
            'carbs_pct' => round($carbs * 4 / $kcal * 100, 1),
            'fat_pct' => round($fat * 9 / $kcal * 100, 1),
        ];
    }
}
