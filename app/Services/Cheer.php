<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * Mensagens de incentivo mostradas no topo da tela por alguns segundos
 * depois de registrar uma refeição, água ou atividade física.
 * Cada situação tem algumas variações, sorteadas a cada registro.
 *
 * @return array{kind: string, icon: string, text: string}
 */
class Cheer
{
    public const MESSAGES = [
        'meal' => [
            'Parabéns! Se alimentar é muito importante.',
            'Ótimo! Registrar suas refeições é o primeiro passo para comer melhor.',
            'Refeição anotada! Comer com regularidade mantém sua energia em dia.',
            'Boa! Cada registro te ajuda a conhecer melhor sua alimentação.',
        ],
        'protein' => [
            'Muito bem! Alimentos ricos em proteínas são ótimos para a reconstrução da massa magra.',
            'Mandou bem! Uma refeição rica em proteína ajuda a preservar e construir músculos.',
            'Excelente escolha! Proteína dá saciedade e fortalece sua massa magra.',
        ],
        'fiber' => [
            'Continue assim! Alimentos ricos em fibras beneficiam seu sistema digestivo.',
            'Que refeição! As fibras ajudam o intestino e prolongam a saciedade.',
            'Ótima escolha! Fibras cuidam da sua digestão e ajudam a controlar a fome.',
        ],
        'water' => [
            'Aee! Beba água constantemente no decorrer do dia para fortalecer seu metabolismo.',
            'Isso aí! Mais um copo para manter seu corpo hidratado.',
            'Boa! Espalhe os goles ao longo do dia para manter a hidratação.',
        ],
        'water_steady' => [
            'Muito bem! Beber água constantemente ajuda seu corpo.',
            'Que constância! Hidratação ao longo do dia faz seu corpo funcionar melhor.',
            'Ritmo perfeito! Beber água aos poucos durante o dia é o ideal.',
        ],
        'activity' => [
            'Boa atleta! Atividade física é um ponto positivo para o fortalecimento dos seus músculos.',
            'Mandou ver! Cada treino deixa seus músculos mais fortes.',
            'Que disposição! Movimentar o corpo faz bem para os músculos e para a mente.',
        ],
    ];

    public const ICONS = [
        'meal' => '🍽️',
        'protein' => '💪',
        'fiber' => '🥦',
        'water' => '💧',
        'water_steady' => '💧',
        'activity' => '🏃',
    ];

    public static function make(string $kind): array
    {
        return ['kind' => $kind, 'icon' => self::ICONS[$kind], 'text' => Arr::random(self::MESSAGES[$kind])];
    }

    /**
     * Rica em proteína (≥ 30% das calorias) e leve no resto → 'protein'.
     * Rica em fibras (≥ 6 g na refeição) e leve no resto → 'fiber'.
     * Senão → 'meal'.
     */
    public static function mealKind(Meal $meal): string
    {
        $kcal = max(1, (float) $meal->calories);
        $lowSugar = $meal->sugar_g * 4 / $kcal <= 0.10;

        if ($meal->protein_pct >= 30 && $meal->carbs_pct <= 40 && $meal->fat_pct <= 35 && $lowSugar) {
            return 'protein';
        }

        if ($meal->fiber_g >= 6 && $meal->carbs_pct <= 60 && $meal->fat_pct <= 30 && $lowSugar) {
            return 'fiber';
        }

        return 'meal';
    }

    public static function forMeal(Meal $meal): array
    {
        return self::make(self::mealKind($meal));
    }

    /**
     * "Constante" = hoje já são 3 ou mais registros, espalhados por pelo menos 2 horas
     * e sem intervalo maior que 3 horas entre um e outro.
     */
    public static function waterKind(User $user, CarbonImmutable $date): string
    {
        if (! $date->isToday()) {
            return 'water';
        }

        $times = $user->waterLogs()
            ->whereBetween('logged_at', [$date->startOfDay(), $date->endOfDay()])
            ->orderBy('logged_at')
            ->pluck('logged_at');

        if ($times->count() < 3 || $times->first()->diffInMinutes($times->last()) < 120) {
            return 'water';
        }

        foreach ($times->sliding(2) as $pair) {
            if ($pair->first()->diffInMinutes($pair->last()) > 180) {
                return 'water';
            }
        }

        return 'water_steady';
    }

    public static function forWater(User $user, CarbonImmutable $date): array
    {
        return self::make(self::waterKind($user, $date));
    }
}
