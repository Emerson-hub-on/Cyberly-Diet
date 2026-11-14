<?php

/*
| Modal "Nutrientes do dia": título da lista de alimentos, dica e exemplos
| [alimento, porção, gramas do nutriente na porção]. Valores aproximados (tabela TACO/USDA).
*/
return [
    'protein' => [
        'title' => 'Alimentos ricos em proteína',
        'tip' => 'Distribua a proteína entre as refeições do dia: ajuda a manter a massa magra e dá saciedade.',
        'foods' => [
            ['Peito de frango grelhado', '100 g', 32],
            ['Carne bovina magra', '100 g', 30],
            ['Atum em lata', '100 g', 26],
            ['Ovos', '2 unidades', 12],
            ['Iogurte grego natural', '1 pote (170 g)', 15],
            ['Queijo cottage', '100 g', 11],
            ['Lentilha cozida', '100 g', 9],
            ['Feijão cozido', '1 concha (100 g)', 5],
        ],
    ],
    'carbs' => [
        'title' => 'Boas fontes de carboidrato',
        'tip' => 'Prefira as versões integrais e com casca: liberam energia aos poucos e trazem fibras junto.',
        'foods' => [
            ['Mandioca cozida', '100 g', 30],
            ['Arroz integral cozido', '100 g', 26],
            ['Pão integral', '2 fatias', 24],
            ['Banana', '1 média', 23],
            ['Batata-doce cozida', '100 g', 18],
            ['Aveia em flocos', '3 colheres (30 g)', 17],
        ],
    ],
    'fat' => [
        'title' => 'Gorduras boas',
        'tip' => 'São calóricas (9 kcal por grama), então vale medir as porções. Prefira estas às frituras e ultraprocessados.',
        'foods' => [
            ['Abacate', '100 g', 15],
            ['Amendoim', '30 g', 15],
            ['Azeite de oliva', '1 colher de sopa', 13],
            ['Salmão', '100 g', 13],
            ['Castanha-do-pará', '3 unidades', 9],
            ['Semente de chia', '1 colher de sopa', 4],
        ],
    ],
    'sugar' => [
        'title' => 'Onde tem muito açúcar',
        'tip' => 'Estes merecem moderação. O açúcar das frutas inteiras vem junto com fibras e é a melhor escolha para um doce.',
        'foods' => [
            ['Refrigerante', '1 lata (350 ml)', 37],
            ['Suco de caixinha', '1 copo (200 ml)', 20],
            ['Iogurte com sabor', '1 pote (170 g)', 20],
            ['Biscoito recheado', '3 unidades', 15],
            ['Chocolate ao leite', '25 g', 13],
            ['Leite condensado', '1 colher de sopa', 11],
        ],
    ],
    'fiber' => [
        'title' => 'Alimentos ricos em fibras',
        'tip' => 'Ao aumentar as fibras, beba mais água: elas precisam de líquido para ajudar o intestino.',
        'foods' => [
            ['Feijão cozido', '100 g', 8],
            ['Grão-de-bico cozido', '100 g', 8],
            ['Lentilha cozida', '100 g', 8],
            ['Semente de chia', '1 colher de sopa', 5],
            ['Pera com casca', '1 média', 5],
            ['Aveia em flocos', '3 colheres (30 g)', 3],
            ['Brócolis cozido', '100 g', 3],
        ],
    ],
];
