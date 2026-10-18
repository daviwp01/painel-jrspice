/**
 * Build dos assets do formulário público de cadastro.
 *
 *   node scripts/build-embed.mjs
 *
 * Lê a fonte legível em resources/embed/, remove comentários, encolhe
 * identificadores e escreve o arquivo pronto em public/embed/ para ser servido
 * como estático (cacheável por um ano, invalidado pela versão ?v=).
 */
import { build } from 'esbuild';
import { mkdir, readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';

const OUT_DIR = 'public/embed';
const SOURCE_MAPS = false;

await mkdir(OUT_DIR, { recursive: true });

await build({
    entryPoints: ['resources/embed/register-form.js'],
    outfile: `${OUT_DIR}/register-form.min.js`,
    bundle: true,
    minify: true,
    sourcemap: SOURCE_MAPS,
    target: ['es2017'],
    legalComments: 'none',
});

await build({
    entryPoints: ['resources/embed/register-form.css'],
    outfile: `${OUT_DIR}/register-form.min.css`,
    bundle: true,
    minify: true,
    sourcemap: SOURCE_MAPS,
    target: ['es2017'],
    legalComments: 'none',
});

const [js, css] = await Promise.all([
    readFile(`${OUT_DIR}/register-form.min.js`, 'utf8'),
    readFile(`${OUT_DIR}/register-form.min.css`, 'utf8'),
]);

const fingerprint = createHash('sha256').update(js + css).digest('hex').slice(0, 8);

const hasComments = (source) => /(^|\n)\s*(\/\/|\/\*)/.test(source.replace(/url\([^)]*\)/g, ''));

if (hasComments(js) || hasComments(css)) {
    throw new Error('O build ainda contém comentários — revise a fonte.');
}

console.log(`register-form.min.js   ${js.length} bytes`);
console.log(`register-form.min.css  ${css.length} bytes`);
console.log(`versão recomendada     ${fingerprint}`);
console.log('\nAtualize EMBED_ASSET_VERSION com a versão acima para invalidar o cache.');
