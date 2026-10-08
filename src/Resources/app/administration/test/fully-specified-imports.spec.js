import { describe, expect, it } from 'vitest';
import { readdirSync, readFileSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

// package.json declares "type": "module", so the webpack build of Shopware 6.6 treats every file as
// strict ESM and fails on relative imports without an extension. Vitest and esbuild resolve them
// anyway, which is why this has to be checked on the source itself. Specs are not bundled by webpack.
const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const relativeImport = /(?:\bfrom\s*|\bimport\s*\(\s*|^\s*import\s+)(['"])(\.{1,2}\/[^'"]*)\1/gm;

function collectFiles(dir) {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const path = join(dir, entry.name);

        if (entry.isDirectory()) {
            return collectFiles(path);
        }

        return entry.name.endsWith('.js') && !entry.name.endsWith('.spec.js') ? [path] : [];
    });
}

describe('relative imports', () => {
    it('are fully specified', () => {
        const offenders = collectFiles(join(root, 'src'))
            .flatMap((file) =>
                [...readFileSync(file, 'utf8').matchAll(relativeImport)]
                    .map((match) => match[2])
                    .filter((specifier) => !/\.[a-z]+$/i.test(specifier))
                    .map((specifier) => `${relative(root, file)}: ${specifier}`)
            );

        expect(offenders).toEqual([]);
    });
});
