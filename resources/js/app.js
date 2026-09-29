import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

let currentPharmacyName = 'Apotek Medika Sore';

function updateSiteHeader(props) {
    if (!props?.app_settings) return;
    
    const pharmacyName = props.app_settings.pharmacy_name || 'Apotek Medika Sore';
    currentPharmacyName = pharmacyName;
    
    const pharmacyLogo = props.app_settings.pharmacy_logo || '/Assets/img/LOGO.svg';
    let faviconLink = document.getElementById('app-favicon');
    if (!faviconLink) {
        faviconLink = document.createElement('link');
        faviconLink.id = 'app-favicon';
        faviconLink.rel = 'icon';
        document.head.appendChild(faviconLink);
    }
    if (faviconLink.getAttribute('href') !== pharmacyLogo) {
        faviconLink.href = pharmacyLogo;
    }
}

router.on('navigate', (event) => {
    updateSiteHeader(event.detail.page.props);
});

createInertiaApp({
    title: (title) => {
        const name = currentPharmacyName || 'Apotek Medika Sore';
        if (!title) return name;
        if (title === name || title.endsWith(`- ${name}`)) return title;
        return `${title} - ${name}`;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        updateSiteHeader(props.initialPage.props);
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
