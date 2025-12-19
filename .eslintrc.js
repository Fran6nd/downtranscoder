module.exports = {
    extends: [
        'eslint:recommended',
        'plugin:vue/recommended'
    ],
    parserOptions: {
        ecmaVersion: 2020,
        sourceType: 'module'
    },
    env: {
        browser: true,
        es6: true,
        node: true
    },
    rules: {
        'vue/html-indent': ['error', 4],
        'vue/max-attributes-per-line': 'off',
        'no-console': process.env.NODE_ENV === 'production' ? 'warn' : 'off',
        'no-debugger': process.env.NODE_ENV === 'production' ? 'warn' : 'off'
    }
};
