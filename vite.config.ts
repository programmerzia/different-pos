import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'tools/**',
            'docs/**',
            'e2e/.gitkeep',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        plugins: ['typescript', 'react', 'jsx-a11y', 'import', 'oxc'],
        rules: {
            'react/no-danger': 'error',
            // Keyboard-first app: focusing the primary field on open is intentional.
            'jsx-a11y/no-autofocus': 'off',
            'react/rules-of-hooks': 'error',
            'react/exhaustive-deps': 'error',
        },
        overrides: [
            {
                // The POS register must work offline and never depend on
                // Inertia page visits.
                files: ['resources/js/pos/**'],
                rules: {
                    'no-restricted-imports': [
                        'error',
                        {
                            patterns: [
                                {
                                    group: ['@inertiajs/*'],
                                    message:
                                        'resources/js/pos must not import @inertiajs/*: the POS is offline-first.',
                                },
                            ],
                        },
                    ],
                },
            },
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['./resources/js/test-setup.ts'],
        include: ['resources/js/**/*.test.{ts,tsx}'],
        passWithNoTests: true,
        alias: { '@': new URL('./resources/js', import.meta.url).pathname },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            '*.md',
            'compose.yaml',
            'index.html',
            'tsconfig.json',
            'tests/**',
            'tools/**',
            'docs/**',
            'vendor/**',
            'node_modules/**',
            'public/**',
            'storage/**',
            'bootstrap/**',
            'resources/js/actions/**',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
