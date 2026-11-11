<?php

namespace Tests\Feature;

use App\Services\Analyzers\GeminiMealAnalyzer;
use App\Services\MealAnalyzer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GeminiAnalyzerTest extends TestCase
{
    private const RESULT = [
        'is_food' => true, 'title' => 'Feijoada', 'items' => [], 'confidence' => 'media', 'notes' => '',
        'totals' => ['calories' => 700, 'protein_g' => 35, 'carbs_g' => 60, 'sugar_g' => 2, 'fat_g' => 30, 'fiber_g' => 12],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['diet.ai_provider' => 'gemini', 'diet.gemini.api_key' => 'test-key', 'diet.gemini.models' => ['modelo-a', 'modelo-b']]);
    }

    private function ok(): array
    {
        return ['candidates' => [[
            'finishReason' => 'STOP',
            'content' => ['parts' => [['text' => 'pensando...', 'thought' => true], ['text' => json_encode(self::RESULT)]]],
        ]]];
    }

    public function test_provider_binding_uses_gemini_by_default(): void
    {
        $this->assertInstanceOf(GeminiMealAnalyzer::class, app(MealAnalyzer::class));
    }

    public function test_sends_image_and_schema_and_parses_answer(): void
    {
        Http::fake(['*modelo-a:generateContent' => Http::response($this->ok())]);

        $result = app(MealAnalyzer::class)->analyze('bytes', 'image/jpeg', 'Almoço', 'sem óleo');

        $this->assertSame(700, (int) $result['totals']['calories']);
        $this->assertSame('modelo-a', $result['model']);

        Http::assertSent(function (Request $request) {
            $parts = $request['contents'][0]['parts'];

            return $request->hasHeader('x-goog-api-key', 'test-key')
                && $parts[0]['inlineData'] === ['mimeType' => 'image/jpeg', 'data' => base64_encode('bytes')]
                && str_contains($parts[1]['text'], 'sem óleo')
                && $request['generationConfig']['responseMimeType'] === 'application/json'
                && isset($request['generationConfig']['responseJsonSchema']['properties']['totals']);
        });
    }

    public function test_falls_back_to_next_model_when_quota_is_exhausted(): void
    {
        Http::fake([
            '*modelo-a:generateContent' => Http::response(['error' => ['code' => 429, 'message' => 'Quota exceeded']], 429),
            '*modelo-b:generateContent' => Http::response($this->ok()),
        ]);

        $result = app(MealAnalyzer::class)->analyze('bytes', 'image/jpeg', 'Jantar');

        $this->assertSame('modelo-b', $result['model']);
    }

    public function test_friendly_error_when_all_models_are_out_of_quota(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 429, 'message' => 'Quota exceeded']], 429)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Limite gratuito do Gemini');

        app(MealAnalyzer::class)->analyze('bytes', 'image/jpeg', 'Jantar');
    }

    public function test_invalid_key_message(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 400, 'message' => 'API key not valid. Please pass a valid API key.']], 400)]);

        $this->expectExceptionMessage('Chave do Gemini inválida');

        app(MealAnalyzer::class)->analyze('bytes', 'image/jpeg', 'Jantar');
    }

    public function test_missing_key_message(): void
    {
        config(['diet.gemini.api_key' => null]);

        $this->expectExceptionMessage('GEMINI_API_KEY');

        app(MealAnalyzer::class)->analyze('bytes', 'image/jpeg', 'Jantar');
    }
}
