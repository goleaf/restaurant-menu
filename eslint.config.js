import js from '@eslint/js';
import globals from 'globals';
import promise from 'eslint-plugin-promise';

export default [
    { ignores: ['vendor/**', 'node_modules/**', 'packages/**', 'flux-pro/**', 'public/**', 'storage/**'] },
    {
        files: ['resources/js/**/*.js', 'resources/build/**/*.js', 'tests/*.mjs', 'tests/Support/*.mjs', '*.config.js'],
        ...js.configs.recommended,
        languageOptions: { ecmaVersion: 'latest', sourceType: 'module', globals: { ...globals.browser, ...globals.node } },
        plugins: { promise },
        rules: {
            'no-unused-vars': ['error', { argsIgnorePattern: '^_', caughtErrors: 'none' }],
            'no-constant-binary-expression': 'error',
            'promise/catch-or-return': ['error', { allowFinally: true }],
            'promise/no-return-wrap': 'error',
            'promise/no-multiple-resolved': 'error',
        },
    },
];
