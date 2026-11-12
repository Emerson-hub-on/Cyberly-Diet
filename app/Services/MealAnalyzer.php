<?php

namespace App\Services;

/**
 * Contrato dos analisadores de foto de refeição.
 * A implementação é escolhida por AI_PROVIDER no .env (gemini | anthropic).
 *
 * @phpstan-type Analysis array{
 *     is_food: bool, title: string, confidence: string, notes: string, model: string,
 *     items: list<array{name: string, portion: string, calories: float, protein_g: float, carbs_g: float, sugar_g: float, fat_g: float, fiber_g: float}>,
 *     totals: array{calories: float, protein_g: float, carbs_g: float, sugar_g: float, fat_g: float, fiber_g: float}
 * }
 */
abstract class MealAnalyzer
{
    protected const SYSTEM_PROMPT = <<<'TXT'
    Você é um nutricionista brasileiro especialista em estimar a composição nutricional de refeições a partir de fotos.

    Ao receber a foto de um prato:
    1. Identifique cada alimento visível (considere a culinária brasileira: arroz, feijão, farofa, carnes, saladas etc.).
    2. Estime a porção de cada um em gramas ou medidas caseiras, usando o tamanho do prato, talheres e outros objetos como referência de escala.
    3. Calcule calorias (kcal), proteínas, carboidratos, açúcares, gorduras e fibras de cada item com base em tabelas como TACO/USDA, e some os totais.
    4. Considere óleos e molhos de preparo que não aparecem claramente, mas costumam estar presentes.

    Seja realista: prefira a estimativa mais provável a valores extremos. Os totais devem ser iguais à soma dos itens.
    Se a imagem não mostrar comida, defina is_food como false, deixe items vazio e zere os totais.
    Escreva nomes e observações em português do Brasil.
    TXT;

    /**
     * @return Analysis
     *
     * @throws \RuntimeException com mensagem amigável para o usuário
     */
    abstract public function analyze(string $imageBytes, string $mediaType, string $mealTypeLabel, ?string $userNote = null): array;

    protected function userPrompt(string $mealTypeLabel, ?string $userNote): string
    {
        $prompt = "Refeição: {$mealTypeLabel}.";
        if (filled($userNote)) {
            $prompt .= "\nObservação do usuário (use para refinar a estimativa): {$userNote}";
        }

        return $prompt."\nAnalise a foto e estime os valores nutricionais.";
    }

    /** Valida o JSON devolvido pela IA. */
    protected function decode(?string $text, string $model): array
    {
        $json = $text !== null ? json_decode($text, true) : null;

        if (! is_array($json) || ! isset($json['totals']) || ! is_array($json['totals'])) {
            throw new \RuntimeException('Não foi possível interpretar a resposta da IA. Tente novamente.');
        }

        $json['is_food'] = (bool) ($json['is_food'] ?? false);
        $json['items'] = is_array($json['items'] ?? null) ? $json['items'] : [];
        $json['model'] = $model;

        return $json;
    }

    protected static function schema(): array
    {
        $num = ['type' => 'number'];

        return [
            'type' => 'object',
            'properties' => [
                'is_food' => ['type' => 'boolean', 'description' => 'A imagem mostra comida ou bebida?'],
                'title' => ['type' => 'string', 'description' => 'Nome curto do prato, ex.: "Arroz, feijão e frango grelhado"'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'portion' => ['type' => 'string', 'description' => 'Porção estimada, ex.: "150 g" ou "2 colheres de sopa"'],
                            'calories' => $num,
                            'protein_g' => $num,
                            'carbs_g' => $num,
                            'sugar_g' => $num,
                            'fat_g' => $num,
                            'fiber_g' => $num,
                        ],
                        'required' => ['name', 'portion', 'calories', 'protein_g', 'carbs_g', 'sugar_g', 'fat_g', 'fiber_g'],
                        'additionalProperties' => false,
                    ],
                ],
                'totals' => [
                    'type' => 'object',
                    'properties' => [
                        'calories' => $num,
                        'protein_g' => $num,
                        'carbs_g' => $num,
                        'sugar_g' => $num,
                        'fat_g' => $num,
                        'fiber_g' => $num,
                    ],
                    'required' => ['calories', 'protein_g', 'carbs_g', 'sugar_g', 'fat_g', 'fiber_g'],
                    'additionalProperties' => false,
                ],
                'confidence' => ['type' => 'string', 'enum' => ['baixa', 'media', 'alta']],
                'notes' => ['type' => 'string', 'description' => 'Observações curtas sobre a estimativa ou a qualidade nutricional'],
            ],
            'required' => ['is_food', 'title', 'items', 'totals', 'confidence', 'notes'],
            'additionalProperties' => false,
        ];
    }
}
