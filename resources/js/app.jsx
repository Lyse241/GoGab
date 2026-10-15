import '../css/app.css';
import './bootstrap';

import { focusFirstError } from '@/Components/FormErrors';
import { ToastProvider } from '@/Components/UI/Toast';
import { CartProvider } from '@/Contexts/CartContext';
import { NeighborhoodProvider } from '@/Contexts/NeighborhoodContext';
import { NotificationsProvider } from '@/Contexts/NotificationsContext';
import { unsubscribeDevice } from '@/utils/push';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Gogab';

// PWA : le service worker (public/sw.js) n'est activé qu'en production, pour ne pas gêner le
// développement (Vite) ; il ne met en cache que les fichiers compilés et une page « hors ligne ».
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

// Déconnexion : l'appareil est d'abord désabonné des notifications push, pour que le compte
// suivant sur un téléphone partagé ne reçoive pas celles de la personne qui part.
let loggingOut = false;
router.on('before', (event) => {
    const { method, url } = event.detail.visit;
    if (loggingOut || method !== 'post' || url.pathname !== '/logout') {
        return;
    }

    event.preventDefault();
    loggingOut = true;
    Promise.race([unsubscribeDevice(), new Promise((resolve) => setTimeout(resolve, 3000))]).finally(() => {
        router.post('/logout');
        loggingOut = false;
    });
});

// Toute erreur de validation (n'importe quel formulaire) : focus sur le premier champ en erreur.
router.on('error', (event) => focusFirstError(event.detail.errors));

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
