import '../css/app.css';
import './bootstrap';

import { ToastProvider } from '@/Components/UI/Toast';
import { CartProvider } from '@/Contexts/CartContext';
import { NeighborhoodProvider } from '@/Contexts/NeighborhoodContext';
import { NotificationsProvider } from '@/Contexts/NotificationsContext';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        const initialProps = props.initialPage.props;

        // Les providers entourent l'App : toasts, notifications, quartier et panier survivent
        // aux navigations Inertia. Les messages flash de la première page sont affichés en toasts,
        // puis ceux de chaque visite.
        root.render(
            <ToastProvider initialFlash={initialProps.flash}>
                <NotificationsProvider initialAuth={initialProps.auth}>
                    <NeighborhoodProvider>
                        <CartProvider>
                            <App {...props} />
                        </CartProvider>
                    </NeighborhoodProvider>
                </NotificationsProvider>
            </ToastProvider>,
        );
    },
    progress: {
        color: '#FBBF24',
        delay: 150,
    },
});
