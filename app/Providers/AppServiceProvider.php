<?php

namespace App\Providers;

use App\Services\Analyzers\ClaudeMealAnalyzer;
use App\Services\Analyzers\GeminiMealAnalyzer;
use App\Services\MealAnalyzer;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MealAnalyzer::class, fn () => match (config('diet.ai_provider')) {
            'anthropic', 'claude' => new ClaudeMealAnalyzer,
            default => new GeminiMealAnalyzer,
        });
    }

    public function boot(): void
    {
        Carbon::setLocale('pt_BR');
        CarbonImmutable::setLocale('pt_BR');

        // Na Vercel o app roda atrás de proxy HTTPS
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
