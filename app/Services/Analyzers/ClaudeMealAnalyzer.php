<?php

namespace App\Services\Analyzers;

use Anthropic\Beta\Messages\BetaBase64ImageSource;
use Anthropic\Beta\Messages\BetaImageBlockParam;
use Anthropic\Beta\Messages\BetaOutputConfig;
use Anthropic\Beta\Messages\BetaTextBlockParam;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Services\MealAnalyzer;
use RuntimeException;

/**
 * Análise da foto com o Claude (Anthropic API — pago por uso).
 * Ative com AI_PROVIDER=anthropic e ANTHROPIC_API_KEY no .env.
 */
class ClaudeMealAnalyzer extends MealAnalyzer
{
    public function analyze(string $imageBytes, string $mediaType, string $mealTypeLabel, ?string $userNote = null): array
    {
        $apiKey = config('diet.anthropic.api_key');
        if (blank($apiKey)) {
            throw new RuntimeException('ANTHROPIC_API_KEY não configurada no arquivo .env.');
        }

        $client = new Client(apiKey: $apiKey);

        try {
            $message = $client->beta->messages->create(
                model: config('diet.anthropic.model'),
                maxTokens: 16000,
                system: self::SYSTEM_PROMPT,
                messages: [[
                    'role' => 'user',
                    'content' => [
                        BetaImageBlockParam::with(
                            source: BetaBase64ImageSource::with(data: base64_encode($imageBytes), mediaType: $mediaType),
                        ),
                        BetaTextBlockParam::with(text: $this->userPrompt($mealTypeLabel, $userNote)),
                    ],
                ]],
                outputConfig: BetaOutputConfig::with(
                    effort: config('diet.anthropic.effort'),
                    format: ['type' => 'json_schema', 'schema' => self::schema()],
                ),
                // Se o modelo principal recusar, o servidor tenta automaticamente um modelo alternativo.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException) {
            throw new RuntimeException('Chave da API Anthropic inválida. Verifique ANTHROPIC_API_KEY.');
        } catch (RateLimitException) {
            throw new RuntimeException('Limite de requisições da IA atingido. Tente novamente em instantes.');
        } catch (BadRequestException $e) {
            throw new RuntimeException('A IA não aceitou a imagem: '.$e->getMessage());
        } catch (APIStatusException) {
            throw new RuntimeException('Serviço de IA indisponível no momento. Tente novamente.');
        } catch (APIConnectionException) {
            throw new RuntimeException('Sem conexão com o serviço de IA. Verifique sua internet.');
        }

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('A IA não conseguiu analisar esta imagem. Tente outra foto.');
        }
        if ($message->stopReason === 'max_tokens') {
            throw new RuntimeException('A resposta da IA veio incompleta. Tente novamente.');
        }

        $text = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text = $block->text;
                break;
            }
        }

        return $this->decode($text, $message->model);
    }
}
