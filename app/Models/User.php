<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'calorie_goal', 'water_goal_ml', 'weight_goal_kg', 'height_cm', 'sex', 'birth_date',
    'bmr_kcal', 'protein_goal_g', 'carbs_goal_g', 'fat_goal_g', 'fiber_goal_g'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'calorie_goal' => 'integer',
            'water_goal_ml' => 'integer',
            'weight_goal_kg' => 'float',
            'height_cm' => 'integer',
            'birth_date' => 'date',
            'bmr_kcal' => 'integer',
            'protein_goal_g' => 'integer',
            'carbs_goal_g' => 'integer',
            'fat_goal_g' => 'integer',
            'fiber_goal_g' => 'integer',
        ];
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    public function weightLogs(): HasMany
    {
        return $this->hasMany(WeightLog::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function waterLogs(): HasMany
    {
        return $this->hasMany(WaterLog::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** Grava um evento no log do usuário. */
    public function logEvent(string $type, ?object $subject = null, array $payload = []): Event
    {
        return $this->events()->create([
            'type' => $type,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject->id ?? null,
            'payload' => $payload,
        ]);
    }
}
