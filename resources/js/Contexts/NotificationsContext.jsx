import { useToast } from '@/Components/UI/Toast';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';

export const POLL_INTERVAL = 15000;

const NotificationsContext = createContext(null);

/**
 * Lien interne uniquement : les notifications ne font jamais sortir de Gogab.
 */
export function internalUrl(url) {
    if (!url) {
        return null;
    }

    try {
        const target = new URL(url, window.location.origin);

        return target.origin === window.location.origin ? target.pathname + target.search + target.hash : null;
    } catch {
        return null;
    }
}

/**
 * Notifications in-app de l'utilisateur connecté, sans WebSocket :
 * interrogation de /notifications/unread toutes les 15 s (en pause quand l'onglet est caché),
 * toast à l'arrivée d'une nouvelle notification.
 *
 * Placé autour de l'App (app.jsx) : un seul minuteur, qui survit aux navigations Inertia.
 * `initialAuth` = props.auth de la première page ; ensuite, suivi via les visites Inertia.
 */
export function NotificationsProvider({ initialAuth, children }) {
    const toast = useToast();
    const [loggedIn, setLoggedIn] = useState(Boolean(initialAuth?.user));
    const [unreadCount, setUnreadCount] = useState(initialAuth?.unread_notifications ?? 0);
    const [items, setItems] = useState([]);
    const [loaded, setLoaded] = useState(false);
    // Identifiants déjà vus : null tant que le premier chargement n'a pas eu lieu (pas de toast au démarrage).
    const seenIds = useRef(null);

    const announce = useCallback(
        (notifications) => {
            if (seenIds.current === null) {
                seenIds.current = new Set(notifications.map((notification) => notification.id));
                return;
            }

            const fresh = notifications.filter((notification) => !notification.read && !seenIds.current.has(notification.id));
            notifications.forEach((notification) => seenIds.current.add(notification.id));

            if (fresh.length === 1) {
                toast.show(fresh[0].type, fresh[0].message, { title: fresh[0].title });
            } else if (fresh.length > 1) {
                toast.info(`Vous avez ${fresh.length} nouvelles notifications.`, { title: 'Notifications' });
            }
        },
        [toast],
    );

    const refresh = useCallback(async () => {
        try {
            const { data } = await axios.get(route('notifications.unread'));
            setUnreadCount(data.unread_count);
            setItems(data.notifications);
            setLoaded(true);
            announce(data.notifications);
        } catch (error) {
            // Session expirée : on arrête d'interroger le serveur.
            if ([401, 419].includes(error.response?.status)) {
                setLoggedIn(false);
            }
        }
    }, [announce]);

    const countRef = useRef(unreadCount);
    countRef.current = unreadCount;

    // Connexion / déconnexion suivies à chaque visite Inertia.
    // Le compteur partagé n'est qu'un indice : lors d'un rechargement partiel (`only`), Inertia
    // renvoie les anciennes props fusionnées, donc une valeur périmée. S'il diffère, on redemande
    // le vrai compteur au serveur plutôt que de l'appliquer tel quel.
    useEffect(
        () =>
            router.on('success', (event) => {
                const auth = event.detail.page.props.auth;
                setLoggedIn(Boolean(auth?.user));

                if (auth?.user && typeof auth.unread_notifications === 'number' && auth.unread_notifications !== countRef.current) {
                    refresh();
                }
            }),
        [refresh],
    );

    // Interrogation périodique tant qu'un utilisateur est connecté.
    useEffect(() => {
        if (!loggedIn) {
            seenIds.current = null;
            setItems([]);
            setLoaded(false);
            setUnreadCount(0);
            return;
        }

        refresh();

        const timer = setInterval(() => {
            if (!document.hidden) {
                refresh();
            }
        }, POLL_INTERVAL);

        const onVisible = () => {
            if (!document.hidden) {
                refresh();
            }
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [loggedIn, refresh]);

    const markRead = useCallback(async (id) => {
        // Mise à jour immédiate de l'interface, confirmée par le serveur.
        setItems((current) => current.map((item) => (item.id === id ? { ...item, read: true } : item)));
        setUnreadCount((count) => Math.max(0, count - 1));

        try {
            const { data } = await axios.post(route('notifications.read', id));
            setUnreadCount(data.unread_count);
        } catch {
            refresh();
        }
    }, [refresh]);

    const markAllRead = useCallback(async () => {
        setItems((current) => current.map((item) => ({ ...item, read: true })));
        setUnreadCount(0);

        try {
            const { data } = await axios.post(route('notifications.read-all'));
            setUnreadCount(data.unread_count);
        } catch {
            refresh();
        }
    }, [refresh]);

    /**
     * Marque comme lue puis ouvre le lien de la notification (s'il est interne).
     */
    const open = useCallback(
        async (notification) => {
            if (!notification.read) {
                await markRead(notification.id);
            }

            const url = internalUrl(notification.url);
            if (url) {
                router.visit(url);
            }
        },
        [markRead],
    );

    const value = useMemo(
        () => ({ enabled: loggedIn, unreadCount, items, loaded, refresh, markRead, markAllRead, open }),
        [loggedIn, unreadCount, items, loaded, refresh, markRead, markAllRead, open],
    );

    return <NotificationsContext.Provider value={value}>{children}</NotificationsContext.Provider>;
}

export function useNotifications() {
    const context = useContext(NotificationsContext);

    if (!context) {
        throw new Error('useNotifications doit être utilisé dans un <NotificationsProvider>.');
    }

    return context;
}
