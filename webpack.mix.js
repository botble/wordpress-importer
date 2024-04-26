const mix = require('laravel-mix')
const path = require('path')

const directory = path.basename(path.resolve(__dirname))
const source = `platform/plugins/${directory}`
const dist = `public/vendor/core/plugins/${directory}`

mix
    .js(`${source}/resources/assets/js/wordpress-importer.js`, `${dist}/js`)
    .sass(`${source}/resources/assets/scss/wordpress-importer.scss`, `${dist}/css`)

if (mix.inProduction()) {
    mix
        .copy(`${dist}/js/wordpress-importer.js`, `${source}/public/js`)
        .copy(`${dist}/css/wordpress-importer.css`, `${source}/public/css`)
}
