<?php

namespace App\Services\Analyzers;

use App\Services\MealAnalyzer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Análise da foto com o Google Gemini (API generateContent, nível gratuito).
 *
 * O nível gratuito oscila bastante (503 "high demand" ou requisições que travam). Por isso:
 * - cada tentativa tem tempo limite curto;
 * - em sobrecarga/tempo esgotado, tenta o próximo modelo da lista, em rodízio;
 * - tudo dentro de um orçamento total menor que o limite da função na Vercel (60 s).
 */
class GeminiMealAnalyzer extends MealAnalyzer
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /** Códigos HTTP em que vale tentar de novo / outro modelo. */
    private const RETRYABLE = [408, 429, 500, 502, 503, 504];

    public function analyze(string $imageBytes, string $mediaType, string $mealTypeLabel, ?string $userNote = null): array
    {
        $apiKey = config('diet.gemini.api_key');
        if (blank($apiKey)) {
            throw new RuntimeException('GEMINI_API_KEY não configurada no arquivo .env.');
        }

        $models = config('diet.gemini.models');
        $budget = (float) config('diet.gemini.budget_seconds', 50);
        $perAttempt = (float) config('diet.gemini.attempt_timeout', 20);
        $thinkingLevel = config('diet.gemini.thinking_level');

        $schema = self::schema();
        $schema['properties']['items']['maxItems'] = 15; // evita respostas que "não terminam"

        $body = [
            'systemInstruction' => ['parts' => [['text' => self::SYSTEM_PROMPT]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['inlineData' => ['mimeType' => $mediaType, 'data' => base64_encode($imageBytes)]],
                    ['text' => $this->userPrompt($mealTypeLabel, $userNote)],
                ],
            ]],
            'generationConfig' => array_filter([
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => $schema,
                'maxOutputTokens' => 4096,
                'thinkingConfig' => filled($thinkingLevel) ? ['thinkingLevel' => $thinkingLevel] : null,
            ]),
        ];

        $started = microtime(true);
        $dead = [];          // modelos que não existem/não aceitam a requisição
        $noThinking = [];    // modelos que rejeitaram thinkingConfig
        $lastStatus = null;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $alive = array_values(array_diff($models, $dead));
            $remaining = $budget - (microtime(true) - $started);

            if (! $alive || $remaining < 4) {
                break;
            }

            $model = $alive[$attempt % count($alive)];
            $payload = $body;
            if (isset($noThinking[$model])) {
                unset($payload['generationConfig']['thinkingConfig']);
            }

            try {
                $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                    ->connectTimeout(5)
                    ->timeout((int) max(3, min($perAttempt, $remaining)))
                    ->post(sprintf(self::ENDPOINT, $model), $payload);
            } catch (ConnectionException $e) {
                // Tempo esgotado ou falha de rede: tenta o próximo modelo
                report(new RuntimeException("Gemini {$model}: sem resposta ({$e->getMessage()})"));
                $lastStatus = 'timeout';

                continue;
            }

            if ($response->successful()) {
                try {
                    return $this->decode($this->extractText($response->json()), $model);
                } catch (RuntimeException $e) {
                    // Resposta cortada/inválida: vale tentar outro modelo
                    report(new RuntimeException("Gemini {$model}: resposta inválida ({$e->getMessage()})"));
                    $lastStatus = 'invalid';

                    continue;
                }
            }

            $status = $response->status();
            $message = (string) $response->json('error.message', '');
            report(new RuntimeException("Gemini {$model} HTTP {$status}: {$message}"));
            $lastStatus = $status;

            if ($status === 400 && str_contains($message, 'API key')) {
                throw new RuntimeException('Chave do Gemini inválida. Verifique GEMINI_API_KEY.');
            }
            if ($status === 403) {
                throw new RuntimeException('A chave do Gemini não tem permissão para esta API. Verifique no Google AI Studio.');
            }
            if ($status === 400 && stripos($message, 'thinking') !== false && ! isset($noThinking[$model])) {
                $noThinking[$model] = true; // modelo não aceita thinkingLevel: tenta de novo sem

                continue;
            }
            if (in_array($status, self::RETRYABLE, true)) {
                continue;
            }

            // 404 (modelo descontinuado) ou outro 4xx: não insiste neste modelo
            $dead[] = $model;
        }

        throw new RuntimeException(match ($lastStatus) {
            429 => 'Limite gratuito do Gemini atingido. Tente novamente em alguns minutos.',
            'timeout', 500, 502, 503, 504 => 'A IA gratuita do Google está sobrecarregada agora. Tente novamente em instantes.',
            400 => 'A IA não aceitou a imagem. Tente outra foto.',
            default => 'Não foi possível analisar a foto agora. Tente novamente.',
        });
    }

    private function extractText(?array $json): ?string
    {
        if (filled($json['promptFeedback']['blockReason'] ?? null)) {
            throw new RuntimeException('A IA não conseguiu analisar esta imagem. Tente outra foto.');
        }

        $candidate = $json['candidates'][0] ?? null;
        $finish = $candidate['finishReason'] ?? null;

        if ($finish === 'MAX_TOKENS') {
            throw new RuntimeException('A resposta da IA veio incompleta.');
        }
        if ($finish !== null && $finish !== 'STOP') {
            throw new RuntimeException('A IA não conseguiu analisar esta imagem. Tente outra foto.');
        }

        // Ignora partes de "pensamento" e junta o texto da resposta
        $text = collect($candidate['content']['parts'] ?? [])
            ->reject(fn ($part) => $part['thought'] ?? false)
            ->pluck('text')
            ->filter()
            ->implode('');

        return $text !== '' ? $text : null;
    }
}
