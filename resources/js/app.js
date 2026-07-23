//
import '../css/app.css'
import '../css/coi.css'

import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from 'ziggy-js'

import '@fortawesome/fontawesome-free/css/all.min.css'
import '@vueform/multiselect/themes/default.css'

// Taken from the server (config('coi.name')) rather than VITE_APP_NAME: VITE_
// vars are baked in when the assets are built, so whoever ran the build would
// decide the name and an environment that disagrees ships a wrong title
// silently. APP_NAME is deliberately not the source -- it also feeds the
// session cookie and cache prefixes, so renaming it signs everyone out.
const appName = document
    .querySelector('meta[name="app-name"]')
    ?.getAttribute('content')
    || 'Commitment Corner'

createInertiaApp({
    // Without this, Inertia clears the server-rendered `<title inertia>` on
    // hydration for any page that sets no title of its own, leaving the tab
    // blank -- browsers then fall back to showing the URL.
    title: (title) => (title ? `${title} - ${appName}` : appName),

    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),

    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(App, props),
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el)
    },
})