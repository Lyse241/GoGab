/**
 * Notifications push (Web Push) de l'appareil courant. Toutes les fonctions sont sûres : navigateur
 * sans support, service worker absent (développement) ou autorisation refusée donnent une valeur
 * neutre plutôt qu'une exception.
 */

export const pushSupported = () =>
    typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

/** Clé publique VAPID (base64url) → Uint8Array, format attendu par pushManager.subscribe. */
function toKey(base64Url) {
    const padded = (base64Url + '='.repeat((4 - (base64Url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(padded);

    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
}

async function registration() {
    // Attente bornée : sans service worker (npm run dev), `ready` ne se résout jamais.
    return Promise.race([navigator.serviceWorker.ready, new Promise((resolve) => setTimeout(() => resolve(null), 4000))]);
}

export async function currentSubscription() {
    if (!pushSupported()) return null;
    const reg = await registration();

    return reg ? reg.pushManager.getSubscription() : null;
}

/**
 * Demande l'autorisation puis abonne l'appareil et l'enregistre côté serveur.
 * @returns {Promise<'subscribed'|'denied'|'unavailable'>}
 */
export async function subscribeDevice(publicKey) {
    if (!pushSupported() || !publicKey) return 'unavailable';

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return 'denied';

    const reg = await registration();
    if (!reg) return 'unavailable';

    const subscription =
        (await reg.pushManager.getSubscription()) ??
        (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toKey(publicKey) }));

    await window.axios.post(route('push.subscribe'), subscription.toJSON());

    return 'subscribed';
}

/** Désabonne l'appareil (navigateur et serveur). */
export async function unsubscribeDevice() {
    try {
        const subscription = await currentSubscription();
        if (!subscription) return;

        await window.axios.delete(route('push.unsubscribe'), { data: { endpoint: subscription.endpoint } });
        await subscription.unsubscribe();
    } catch {
        // Hors ligne ou session expirée : l'abonnement sera retiré au prochain refus du service.
    }
}
