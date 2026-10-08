// Shopware's webpack config provides `process` as `process/browser`. That request is resolved from the
// importing file, so dependencies in this plugin's node_modules (debug, kleur via mermaid) cannot reach
// the administration's copy. Alias it to an absolute path, which also satisfies fully specified ESM.
module.exports = () => ({
    resolve: {
        alias: {
            'process/browser$': require.resolve('process/browser.js', {
                paths: [process.cwd()],
            }),
        },
    },
});
