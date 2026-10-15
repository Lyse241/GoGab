import Switch from '@/Components/UI/Switch';
import { currentSubscription, pushSupported, subscribeDevice, unsubscribeDevice } from '@/utils/push';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Interrupteur « Notifications sur cet appareil ». L'autorisation n'est demandée qu'au clic de
 * l'utilisateur (jamais automatiquement). N'apparaît que si les push sont configurés côté serveur
 * et pris en charge par le navigateur.
 */
export default function PushToggle({ className }) {
    const publicKey = usePage().props.push?.public_key;
    const [enabled, setEnabled] = useState(false);
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState(null);
    const supported = pushSupported() && Boolean(publicKey);

    useEffect(() => {
        if (!supported) return;
        currentSubscription().then((subscription) => setEnabled(Boolean(subscription && Notification.permission === 'granted')));
    }, [supported]);

    if (!supported) {
        return null;
    }

    const change = async (next) => {
        setLoading(true);
        setMessage(null);

        if (next) {
            const result = await subscribeDevice(publicKey);
            setEnabled(result === 'subscribed');
            if (result === 'denied') {
                setMessage('Notifications bloquées : autorisez-les dans les réglages de votre navigateur.');
            } else if (result === 'unavailable') {
                setMessage('Les notifications ne sont pas disponibles ici. Installez l’application ou utilisez la version en ligne.');
            }
        } else {
            await unsubscribeDevice();
            setEnabled(false);
        }

        setLoading(false);
    };

    return (
        <div className={className}>
            <Switch
                checked={enabled}
                onChange={change}
                loading={loading}
                label="Notifications sur cet appareil"
                description="Recevez vos commandes et offres de livraison même quand l’application est fermée."
            />
            {message && (
                <p role="status" className="mt-2 text-sm text-warning-800">
                    {message}
                </p>
            )}
        </div>
    );
}
