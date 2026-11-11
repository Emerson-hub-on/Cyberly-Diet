// Monta a pasta "dist" com os arquivos estáticos servidos pela Vercel (CSS/JS/fontes/ícones).
// Nenhum .php nem .htaccess vai para lá: assim toda página cai obrigatoriamente na função PHP (api/index.php).
import { cpSync, existsSync, readdirSync, rmSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const src = 'public';
const out = 'dist';
const skip = new Set(['index.php', '.htaccess', 'hot', 'storage']);

rmSync(out, { recursive: true, force: true });
mkdirSync(out, { recursive: true });

for (const entry of readdirSync(src)) {
    if (skip.has(entry) || entry.endsWith('.php')) continue;
    cpSync(join(src, entry), join(out, entry), { recursive: true });
}

if (!existsSync(join(out, 'build', 'manifest.json'))) {
    console.error('dist/build/manifest.json não encontrado — o "vite build" rodou?');
    process.exit(1);
}

console.log('Arquivos estáticos copiados para dist/:', readdirSync(out).join(', '));
