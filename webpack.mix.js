let mix = require('laravel-mix')
let NovaExtension = require('laravel-nova-devtool')

mix.extend('nova', new NovaExtension())

mix
  .setPublicPath('dist')
  .js('resources/js/field.js', 'js')
  .vue({ version: 3 })
  // Only target modern browsers so async/await isn't compiled down to regenerator-runtime.
  .babelConfig({
    presets: [['@babel/preset-env', { forceAllTransforms: false, targets: 'defaults and supports es6-module' }]],
  })
  .nova('pathfindermediagroup/nova-chunked-file')
  .version()
