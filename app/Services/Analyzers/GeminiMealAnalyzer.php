<?php

namespace App\Services\Analyzers;

use App\Services\MealAnalyzer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Análise da foto com o Google Gemini (API generateContent, nível gratuito).
 * Se o modelo principal estiver sem cota (429) ou indisponível, tenta os próximos da lista.
 */
class GeminiMealAnalyzer extends MealAnalyzer
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function analyze(string $imageBytes, string $mediaType, string $mealTypeLabel, ?string $userNote = null): array
    {
        $apiKey = config('diet.gemini.api_key');
        if (blank($apiKey)) {
            throw new RuntimeException('GEMINI_API_KEY não configurada no arquivo .env.');
        }

        $body = [
            'systemInstruction' => ['parts' => [['text' => self::SYSTEM_PROMPT]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['inlineData' => ['mimeType' => $mediaType, 'data' => base64_encode($imageBytes)]],
                    ['text' => $this->userPrompt($mealTypeLabel, $userNote)],
                ],
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => self::schema(),
            ],
        ];

        $models = config('diet.gemini.models');
        $lastError = 'Serviço de IA indisponível no momento. Tente novamente.';

        foreach ($models as $i => $model) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                    ->timeout(90)
                    ->post(sprintf(self::ENDPOINT, $model), $body);
            } catch (ConnectionException) {
                throw new RuntimeException('Sem conexão com o serviço de IA. Verifique sua internet.');
            }

            $hasNext = $i < count($models) - 1;

            if ($response->successful()) {
                return $this->decode($this->extractText($response->json()), $model);
            }

            $status = $response->status();
            $message = (string) $response->json('error.message', '');
            report(new RuntimeException("Gemini {$model} HTTP {$status}: {$message}"));

            if ($status === 400 && str_contains($message, 'API key')) {
                throw new RuntimeException('Chave do Gemini inválida. Verifique GEMINI_API_KEY.');
            }
            if ($status === 403) {
                throw new RuntimeException('A chave do Gemini não tem permissão para esta API. Verifique no Google AI Studio.');
            }

            // Sem cota, modelo inexistente ou sobrecarregado → tenta o próximo modelo
            if (in_array($status, [429, 404, 500, 503], true) && $hasNext) {
                continue;
            }

            $lastError = match (true) {
                $status === 429 => 'Limite gratuito do Gemini atingido por hoje/minuto. Tente novamente mais tarde.',
                $status === 400 => 'A IA não aceitou a imagem. Tente outra foto.',
                default => $lastError,
            };
            break;
        }

        throw new RuntimeException($lastError);
    }

    private function extractText(?array $json): ?string
    {
        if (filled($json['promptFeedback']['blockReason'] ?? null)) {
            throw new RuntimeException('A IA não conseguiu analisar esta imagem. Tente outra foto.');
        }

        $candidate = $json['candidates'][0] ?? null;
        $finish = $candidate['finishReason'] ?? null;

        if ($finish === 'MAX_TOKENS') {
            throw new RuntimeException('A resposta da IA veio incompleta. Tente novamente.');
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
