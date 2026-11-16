#!/usr/bin/env node
/**
 * Generuje klasy narzędziowe edytora wizualnego (odstępy, wyrównanie, kolumny, rozmiar tekstu, ukrywanie)
 * do public/css/lumin-blocks.css, między znacznikami @utilities. Skala jest w resources/js/lib/builder/utilities.json
 * (ten sam plik czyta panel stylu), więc zmiana skali = edycja JSON + `npm run build:utilities`.
 * Responsywność: klasa bazowa (komputer), prefiks `lm-md-` (do 900 px) i `lm-sm-` (do 640 px).
 */
import { readFileSync, writeFileSync } from 'node:fs';

const spec = JSON.parse(
    readFileSync('resources/js/lib/builder/utilities.json', 'utf8'),
);
const { md, sm } = spec.breakpoints;
const file = 'public/css/lumin-blocks.css';

/** Reguły jednego "poziomu" responsywnego; prefix to '' (bazowy), 'md-' albo 'sm-'. */
const rules = (p) => {
    const c = (name) => `.lm-${p}${name}`;
    const out = [];
    const sides = {
        pt: 'padding-top',
        pb: 'padding-bottom',
        mt: 'margin-top',
        mb: 'margin-bottom',
    };

    for (const [key, prop] of Object.entries(sides)) {
        for (const [step, value] of Object.entries(spec.spacing)) {
            out.push(`${c(`${key}-${step}`)} { ${prop}: ${value}; }`);
        }
    }
    for (const [step, value] of Object.entries(spec.gap)) {
        out.push(`${c(`gap-${step}`)} { gap: ${value}; }`);
    }
    for (const [name, value] of Object.entries(spec.size)) {
        out.push(`${c(`text-${name}`)} { font-size: ${value}; }`);
    }
    for (const side of ['left', 'center', 'right']) {
        out.push(`${c(side)} { text-align: ${side}; }`);
    }
    for (const n of [1, 2, 3, 4]) {
        out.push(
            `${c(`cols-${n}`)} { grid-template-columns: repeat(${n}, minmax(0, 1fr)); }`,
        );
    }

    return out;
};

const lines = [
    '/* @utilities:start (wygenerowane przez scripts/build-utilities.mjs, nie edytuj ręcznie) */',
];
lines.push(...rules(''));
lines.push('.lm-items-start { align-items: flex-start; }');
lines.push('.lm-container.lm-narrow { width: min(100% - 3rem, 46rem); }');
lines.push('.lm-rounded { border-radius: var(--lm-radius); }');
lines.push('.lm-shadow { box-shadow: 0 10px 30px -12px rgb(0 0 0 / 0.25); }');
lines.push('.lm-border { border: 1px solid var(--lm-border); }');
lines.push(
    `@media (max-width: ${md}px) {`,
    ...rules('md-').map((r) => `    ${r}`),
    '}',
);
lines.push(
    `@media (max-width: ${sm}px) {`,
    ...rules('sm-').map((r) => `    ${r}`),
    '}',
);
lines.push(
    `@media (min-width: ${md + 1}px) { .lm-hide-desktop { display: none !important; } }`,
);
lines.push(
    `@media (min-width: ${sm + 1}px) and (max-width: ${md}px) { .lm-hide-tablet { display: none !important; } }`,
);
lines.push(
    `@media (max-width: ${sm}px) { .lm-hide-mobile { display: none !important; } }`,
);
lines.push('/* @utilities:end */');

const block = lines.join('\n');
let css = readFileSync(file, 'utf8');
const re = /\/\* @utilities:start[\s\S]*?@utilities:end \*\//;
css = re.test(css)
    ? css.replace(re, () => block)
    : `${css.trimEnd()}\n\n${block}\n`;
writeFileSync(file, css);
console.log(`Zapisano ${lines.length} reguł narzędziowych do ${file}`);
