export default {
    ignoreFiles: ['node_modules/**', 'vendor/**', 'packages/**', 'public/build/**'],
    rules: { 'block-no-empty': true },
    overrides: [
        {
            files: ['resources/css/app.css', 'resources/css/generated-theme.css'],
            rules: {
                'at-rule-no-unknown': [true, { ignoreAtRules: ['source', 'theme', 'custom-variant'] }],
                'declaration-block-no-duplicate-properties': true,
            },
        },
        {
            files: ['resources/scss/**/*.scss'],
            customSyntax: 'postcss-scss',
            plugins: ['stylelint-scss'],
            rules: {
                'at-rule-disallowed-list': ['import', 'extend', 'apply', 'theme', 'source', 'utility', 'reference', 'tailwind'],
                'block-no-empty': true,
                'color-no-invalid-hex': true,
                'declaration-block-no-duplicate-properties': true,
                'property-no-unknown': true,
                'selector-max-id': 0,
                'max-nesting-depth': 3,
                'scss/at-rule-no-unknown': true,
                'scss/load-no-partial-leading-underscore': true,
                'scss/load-partial-extension': 'never',
            },
        },
    ],
};
