import { createInertiaApp } from '@inertiajs/react';
import NotFound from './pages/errors/404';
import Welcome from './pages/welcome';
import Dashboard from './pages/dashboard/index';
import SecuritySettings from './pages/security/settings/security';
import SecurityProfile from './pages/security/settings/profile';
import AppearanceSettings from './pages/security/settings/appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const pages = {
    welcome: Welcome,
    'errors/404': NotFound,
    dashboard: Dashboard,
    'security/settings/security': SecuritySettings,
    'security/settings/profile': SecurityProfile,
    'security/settings/appearance': AppearanceSettings,
};

void createInertiaApp({
    resolve: async (name) => {
        const page = pages[name as keyof typeof pages];

        if (!page) {
            throw new Error(`Inertia page "${name}" is not registered.`);
        }

        return page;
    },
    title: (title) => (title ? `${title} - ${appName}` : appName),
    strictMode: true,
    progress: {
        color: '#4B5563',
    },
});
