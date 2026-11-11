# Cyberly Diet

Diário alimentar no estilo "Evolução": fotografe o prato, a IA (Claude) estima calorias,
proteínas, carboidratos, açúcar, gorduras e fibras, e o painel mostra os percentuais e gráficos.
Também registra peso corporal, atividade física e ingestão de água.

**Stack:** Laravel 13 (PHP 8.3) · Blade + Tailwind CSS 4 · Alpine.js · Chart.js · Claude API (visão)
· SQLite local / Supabase Postgres em produção · Supabase Storage (S3) para as fotos · Vercel.

---

## 1. Rodar localmente

```bash
composer install
npm install
cp .env.example .env        # (já existe no seu computador)
php artisan key:generate    # (já feito)
php artisan migrate
```

Edite o `.env` e coloque sua chave **gratuita** do Gemini
(crie em https://aistudio.google.com/apikey):

```
AI_PROVIDER=gemini
GEMINI_API_KEY=sua-chave
```

> Nível gratuito do Gemini: há limite de uso por minuto/dia, e o Google pode usar o conteúdo
> enviado (as fotos) para melhorar os produtos dele. Se um modelo ficar sem cota, o sistema
> tenta o próximo da lista `GEMINI_MODELS`.
>
> Para usar o Claude (pago), troque para `AI_PROVIDER=anthropic` e preencha `ANTHROPIC_API_KEY`.

Depois, dê dois cliques em **`serve-lan.bat`** (ou rode `npm run build` + `php artisan serve --host=0.0.0.0 --port=8000`).

- No computador: http://localhost:8000
- No celular (mesmo Wi-Fi): `http://IP-DO-PC:8000` — o script mostra o IP (ex.: `http://192.168.1.6:8000`).
  Na primeira vez o Windows pode pedir para liberar o PHP no firewall: permita em **redes privadas**.

No primeiro acesso, crie sua conta em **Criar conta**. Depois disso o cadastro fica fechado
(para abrir para outras pessoas, use `ALLOW_REGISTRATION=true`).

> A câmera funciona pelo celular mesmo em `http://` porque usamos o seletor de arquivos com
> `capture="environment"`. A foto é reduzida no navegador (máx. 1568 px, JPEG) antes do envio.

### Testes

```bash
php artisan test
```

---

## 2. Como funciona o registro de refeição

1. Botão **+** → modal: foto (câmera ou galeria), tipo (Café da manhã, Lanche, Almoço, Jantar), data/hora e observação opcional.
2. `POST /refeicoes` → o analisador configurado (`App\Services\Analyzers\GeminiMealAnalyzer` ou `ClaudeMealAnalyzer`) envia a imagem à IA com um **JSON schema**, e ela devolve itens, porções e totais.
3. O sistema calcula:
   - **% de cada macro nas calorias** (proteína e carbo = 4 kcal/g, gordura = 9 kcal/g);
   - **% da meta diária** que a refeição representa.
4. Grava a refeição (`meals`), a foto no disco configurado e um registro em **`events`** (`meal.created`, com os percentuais e o total do dia).
5. O painel é atualizado: anéis de nutrientes, barra de meta e gráfico de 7 dias.

Os eventos aparecem em **Perfil → Registro de eventos** (também peso, atividade, água e metas).

---

## 3. Banco no Supabase

1. Crie um projeto no [Supabase](https://supabase.com).
2. Em **Project Settings → Database → Connection pooling**, copie host, porta (**6543**, modo transação) e usuário (`postgres.SEU_REF`).
3. Ative a extensão `pdo_pgsql` no PHP local (Laragon: menu **PHP → Extensions → pdo_pgsql**).
4. No `.env`:

```
DB_CONNECTION=pgsql
DB_HOST=aws-0-sa-east-1.pooler.supabase.com
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.SEU_REF
DB_PASSWORD=sua-senha
DB_SSLMODE=require
DB_EMULATE_PREPARES=true
```

5. `php artisan migrate` cria as tabelas no Supabase.

### Fotos no Supabase Storage

1. **Storage → New bucket** → `meal-photos` (privado).
2. **Storage → Settings → S3 Connection**: ative e crie uma *access key*.
3. No `.env` (ou nas variáveis da Vercel):

```
MEAL_PHOTOS_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=sa-east-1
AWS_BUCKET=meal-photos
AWS_ENDPOINT=https://SEU_REF.supabase.co/storage/v1/s3
AWS_USE_PATH_STYLE_ENDPOINT=true
```

As fotos sempre passam pela rota autenticada `/refeicoes/{id}/foto`, então o bucket pode continuar privado.

---

## 4. Deploy na Vercel

O projeto já tem `vercel.json` (runtime comunitário `vercel-php`) e `api/index.php`.

1. Suba o projeto para um repositório Git (o `.env` **não** vai junto) e importe na Vercel.
2. Em **Settings → Environment Variables**, cadastre:
   `APP_KEY` (o mesmo do `.env`), `APP_URL`, `GEMINI_API_KEY`, `DB_HOST`, `DB_PORT`,
   `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`,
   `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`.
   (As demais — `APP_ENV`, caches em `/tmp`, `MEAL_PHOTOS_DISK=s3`, `DB_CONNECTION=pgsql` — já estão no `vercel.json`.)
3. Rode as migrations a partir do seu PC apontando para o Supabase (seção 3).

Observações:
- A análise da foto leva alguns segundos; `maxDuration` está em 60 s.
- O sistema de arquivos da Vercel é temporário, por isso em produção as fotos **precisam** ir para o Supabase Storage.
- Se a Vercel acusar versão do runtime, atualize `vercel-php@0.7.3` em `vercel.json` para a versão mais recente do projeto [vercel-community/php](https://github.com/vercel-community/php).

---

## Configurações úteis (`.env`)

| Variável | Padrão | Para quê |
|---|---|---|
| `AI_PROVIDER` | `gemini` | `gemini` (grátis) ou `anthropic` (Claude, pago) |
| `GEMINI_MODELS` | `gemini-3.8-flash,gemini-3.5-flash-lite,gemini-2.5-flash` | Modelos Gemini, em ordem de tentativa |
| `ANTHROPIC_MODEL` | `claude-opus-5-5` | Modelo do Claude (se `AI_PROVIDER=anthropic`) |
| `ANTHROPIC_EFFORT` | `medium` | `low` = mais rápido/barato, `high` = mais cuidadoso |
| `MEAL_PHOTOS_DISK` | `local` | `local` ou `s3` (Supabase) |
| `ALLOW_REGISTRATION` | `false` | Libera cadastro de novos usuários |
| `APP_TIMEZONE` | `America/Sao_Paulo` | Fuso usado para "hoje" |
