import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import { cn } from '@/utils/cn';
import { LocateFixed, LocateOff, Play, Square } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

const SEND_EVERY_MS = 8000;
const DEMO_TICK_MS = 3000; // le trajet simulé avance toutes les 3 s
const DEMO_STEP_M = 110; // ~130 km/h de « film accéléré » : un trajet de 2 km dure ~1 min
const DEMO_KEY = 'gogab_demo_gps';

const toRad = (deg) => (deg * Math.PI) / 180;

/** Distance à vol d'oiseau en mètres (haversine). */
function distanceM(a, b) {
    const dLat = toRad(b.lat - a.lat);
    const dLng = toRad(b.lng - a.lng);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) ** 2;

    return 6371000 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

/** Cap (0–359°) de a vers b. */
function bearing(a, b) {
    const y = Math.sin(toRad(b.lng - a.lng)) * Math.cos(toRad(b.lat));
    const x = Math.cos(toRad(a.lat)) * Math.sin(toRad(b.lat)) - Math.sin(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.cos(toRad(b.lng - a.lng));

    return Math.round(((Math.atan2(y, x) * 180) / Math.PI + 360) % 360);
}

/**
 * Partage de la position du livreur pendant sa course : le téléphone envoie sa position toutes les
 * ~8 secondes (la carte du client se met à jour). L'écran reste allumé tant que la course est
 * ouverte (Wake Lock) : un navigateur ne peut pas suivre la position écran éteint.
 *
 * États : starting (en attente de la première position), sharing, denied (autorisation refusée),
 * unavailable (pas de géolocalisation, ou page non sécurisée).
 */
export default function LocationReporter({ orderId, status, store, destination }) {
    // Mode démonstration : sans GPS (ordinateur, soutenance), le trajet du livreur est simulé et envoyé
    // au serveur comme une vraie position : la carte du client et du commerce se met à jour en direct.
    const [demo, setDemo] = useState(() => {
        try {
            return window.sessionStorage.getItem(DEMO_KEY) === '1';
        } catch {
            return false;
        }
    });
    const [state, setState] = useState('starting');
    const [lastSent, setLastSent] = useState(null);
    const latest = useRef(null);
    const sentAt = useRef(0);

    const send = useCallback(async () => {
        const position = latest.current;
        if (!position) return;
        sentAt.current = Date.now();
        try {
            await window.axios.post(route('delivery.orders.location', orderId), position);
            setLastSent(new Date());
            setState('sharing');
        } catch (error) {
            // Course terminée ou annulée entre-temps : le serveur refuse, l'écran se met à jour tout seul.
            if (error.response?.status !== 422 && error.response?.status !== 403) setState('starting');
        }
    }, [orderId]);

    const watch = useCallback(() => {
        if (!('geolocation' in navigator) || !window.isSecureContext) {
            setState('unavailable');
            return () => {};
        }

        const id = navigator.geolocation.watchPosition(
            ({ coords }) => {
                latest.current = {
                    latitude: coords.latitude,
                    longitude: coords.longitude,
                    accuracy: coords.accuracy,
                    heading: Number.isFinite(coords.heading) ? coords.heading : null,
                };
                // Première position : envoi immédiat, ensuite toutes les SEND_EVERY_MS.
                if (Date.now() - sentAt.current >= SEND_EVERY_MS) send();
            },
            (error) => setState(error.code === error.PERMISSION_DENIED ? 'denied' : 'starting'),
            { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 },
        );

        return () => navigator.geolocation.clearWatch(id);
    }, [send]);

    // Cible de la simulation : le commerce avant le retrait, ensuite l'adresse du client.
    const target = status === 'livreur_assigne' ? store : destination;
    const targetRef = useRef(null);
    targetRef.current = target ? { lat: target.lat, lng: target.lng } : null;

    const toggleDemo = (on) => {
        try {
            window.sessionStorage.setItem(DEMO_KEY, on ? '1' : '0');
        } catch {
            // stockage indisponible : le mode reste actif pour cette page seulement
        }
        latest.current = null;
        sentAt.current = 0;
        setState('starting');
        setDemo(on);
    };

    useEffect(() => {
        if (!demo) return undefined;
        let position = null;

        const tick = () => {
            const goal = targetRef.current;
            if (!goal) return setState('unavailable');
            // Départ : à ~1,5 km de la cible, côté terres (nord-est : la côte de Libreville est à l'ouest), puis on avance vers elle.
            position ??= { lat: goal.lat + 0.006, lng: goal.lng + 0.012 };
            const remaining = distanceM(position, goal);
            if (remaining > 25) {
                const ratio = Math.min(1, DEMO_STEP_M / remaining);
                const next = { lat: position.lat + (goal.lat - position.lat) * ratio, lng: position.lng + (goal.lng - position.lng) * ratio };
                latest.current = { latitude: next.lat, longitude: next.lng, accuracy: 10, heading: bearing(position, goal) };
                position = next;
            } else {
                latest.current = { latitude: position.lat, longitude: position.lng, accuracy: 10, heading: null };
            }
            send();
        };

        tick();
        const timer = setInterval(tick, DEMO_TICK_MS);

        return () => clearInterval(timer);
    }, [demo, send, status]);

    useEffect(() => {
        if (demo) return undefined;
        const stopWatching = watch();
        const timer = setInterval(() => {
            if (latest.current && Date.now() - sentAt.current >= SEND_EVERY_MS) send();
        }, SEND_EVERY_MS);

        // Écran allumé pendant la course (redemandé au retour sur l'onglet).
        let lock = null;
        const keepAwake = async () => {
            try {
                lock = (await navigator.wakeLock?.request('screen')) ?? null;
            } catch {
                lock = null;
            }
        };
        const onVisible = () => document.visibilityState === 'visible' && keepAwake();
        keepAwake();
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            stopWatching();
            clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisible);
            lock?.release?.().catch(() => {});
        };
    }, [demo, watch, send]);

    const ok = state === 'sharing';
    const problem = state === 'denied' || state === 'unavailable';

    return (
        <Card padding="sm" className={cn('flex items-start gap-3 px-4', problem && 'bg-warning-50 ring-warning-200')}>
            <span
                className={cn(
                    'mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                    ok ? 'bg-primary-50 text-primary-700' : problem ? 'bg-warning-100 text-warning-800' : 'bg-gray-100 text-gray-600',
                )}
            >
                {problem ? <LocateOff className="h-5 w-5" aria-hidden="true" /> : <LocateFixed className="h-5 w-5" aria-hidden="true" />}
            </span>
            <div className="min-w-0 flex-1" aria-live="polite">
                <p className="font-semibold text-secondary-900">
                    {ok && (demo ? 'Mode démonstration : trajet simulé' : 'Votre position est partagée')}
                    {state === 'starting' && (demo ? 'Démarrage de la simulation…' : 'Recherche de votre position…')}
                    {state === 'denied' && 'Localisation refusée'}
                    {state === 'unavailable' && (demo ? 'Simulation impossible' : 'Localisation indisponible')}
                </p>
                <p className="mt-0.5 text-sm text-gray-600">
                    {ok && `Le client et le commerce voient ${demo ? 'le livreur se déplacer sur la carte' : 'votre arrivée sur la carte'}${lastSent ? ` (envoyée à ${lastSent.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })})` : ''}.${demo ? '' : ' Gardez cet écran ouvert.'}`}
                    {state === 'starting' && (demo ? 'Le livreur part à environ 1,5 km de sa destination.' : 'Autorisez la localisation si votre navigateur la demande.')}
                    {state === 'denied' && 'Autorisez la localisation de Gogab dans les réglages du navigateur, ou lancez la démonstration sans GPS.'}
                    {state === 'unavailable' && (demo ? 'Le commerce ou l’adresse du client n’a pas de position connue.' : 'Pas de GPS sur cet appareil (ou connexion non sécurisée) : lancez la démonstration sans GPS pour voir la carte bouger.')}
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                    {state === 'denied' && !demo && (
                        <Button size="sm" variant="outline" onClick={() => window.location.reload()}>
                            J’ai autorisé : réessayer
                        </Button>
                    )}
                    {!demo ? (
                        <Button size="sm" variant={problem ? 'primary' : 'outline'} icon={Play} onClick={() => toggleDemo(true)}>
                            Démonstration sans GPS
                        </Button>
                    ) : (
                        <Button size="sm" variant="outline" icon={Square} onClick={() => toggleDemo(false)}>
                            Arrêter la simulation
                        </Button>
                    )}
                </div>
            </div>
        </Card>
    );
}
