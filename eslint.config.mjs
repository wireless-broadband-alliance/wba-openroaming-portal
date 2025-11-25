import js from '@eslint/js';
import globals from 'globals';
import eslintConfig from 'eslint/config';
import prettierConfig from 'eslint-config-prettier';

const { defineConfig } = eslintConfig;

export default defineConfig([
    // Apply global ignores
    {
        ignores: [
            'git/',
            'idea/',
            'vscode/',
            'node_modules/',
            'vendor/'
        ],
    },

    // JS files configuration
    {
        files: ['**/*.{js,mjs,cjs}'],
        plugins: { js },
        extends: ['js/recommended', prettierConfig],
        languageOptions: {
            globals: globals.browser,
        },
    },

    // Optional: keep js recommended config
    js.configs.recommended,
]);
