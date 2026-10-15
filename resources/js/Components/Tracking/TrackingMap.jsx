import Card, { CardHeader } from '@/Components/UI/Card';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { useEffect, useRef, useState } from 'react';

const REFRESH_MS = 5000;

// Tracés des icônes (lucide, licence ISC) : écrits ici car les marqueurs Leaflet sont du HTML, pas du React.
const PATHS = {
    bike: '<circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/>',
    store:
        '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/>',
    house:
        '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
};

/** Marqueur rond aux couleurs de la charte (texte blanc sur vert / bleu, jamais sur le jaune). */
function pin(icon, background, color, className = '') {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${PATHS[icon]}</svg>`;

    return L.divIcon({
        className: `gogab-pin ${className}`,
        html: `<span style="background:${background};color:${color}">${svg}</span>`,
        iconSize: [40, 40],
        iconAnchor: [20, 20],
    });
}

const ICONS = {
    courier: () => pin('bike', '#00A86B', '#ffffff', 'gogab-pin--courier'),
    store: () => pin('store', '#1E3A8A', '#ffffff'),
    home: () => pin('house', '#FBBF24', '#1E3A8A'),
};

/**
 * Suivi en direct de la livraison : carte (OpenStreetMap) avec le livreur, le commerce et l'adresse
 * (centre du quartier), et l'arrivée estimée. La position est relue toutes les 5 secondes tant que
 * la course est en cours (statuts livreur assigné, en livraison, arrivé).
 *
 * - orderId : commande suivie
 * - initial : données déjà envoyées par la page (évite un écran vide au chargement)
 * - title / description : textes de l'en-tête (la même carte sert au client et au commerce)
 * Le serveur adapte les données au rôle : le commerce ne reçoit que l'approche du livreur jusqu'au
 * retrait, sans l'adresse du client.
 */
export default function TrackingMap({ orderId, initial, title = 'Suivi en direct', description = 'La position de votre livreur se met à jour automatiquement.' }) {
    const [data, setData] = useState(initial);
    const element = useRef(null);
    const map = useRef(null);
    const layers = useRef({});
    const fitted = useRef(null);
    const fitAll = useRef(null);
    const touched = useRef(false);

    // Interrogation régulière (la page n'est pas rechargée : seule la carte se met à jour).
    useEffect(() => {
        let cancelled = false;
        const load = () =>
            window.axios
                .get(route('orders.tracking', orderId))
                .then((response) => !cancelled && setData(response.data))
                .catch(() => {});

        load();
        const timer = setInterval(() => {
            if (!document.hidden) load();
        }, REFRESH_MS);

        return () => {
            cancelled = true;
            clearInterval(timer);
        };
    }, [orderId]);

    // Création de la carte (une fois).
    useEffect(() => {
        if (!element.current || map.current) return undefined;

        map.current = L.map(element.current, { zoomControl: true, attributionControl: true, scrollWheelZoom: false }).setView([0.4162, 9.4673], 13);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
        }).addTo(map.current);

        // La carte est créée avant que sa feuille de style et sa taille soient connues : on la remesure.
        const observer = new ResizeObserver(() => {
            map.current?.invalidateSize();
            // Taille enfin connue : on cadre tout le trajet, tant que l'utilisateur n'a pas touché la carte.
            if (!touched.current) fitAll.current?.();
        });
        observer.observe(element.current);
        const markTouched = () => {
            touched.current = true;
        };
        element.current.addEventListener('pointerdown', markTouched, { once: true });

        return () => {
            observer.disconnect();
            map.current.remove();
            map.current = null;
            layers.current = {};
            fitted.current = null;
            fitAll.current = null;
            touched.current = false;
        };
    }, []);

    // Mise à jour des marqueurs et du tracé.
    useEffect(() => {
        const current = map.current;
        if (!current || !data) return;

        const place = (key, point, icon, label) => {
            if (!point) {
                layers.current[key]?.remove();
                delete layers.current[key];
                return;
            }
            const latlng = [point.lat, point.lng];
            if (layers.current[key]) {
                layers.current[key].setLatLng(latlng);
            } else {
                layers.current[key] = L.marker(latlng, { icon: ICONS[icon](), title: label, keyboard: false }).addTo(current);
            }
        };

        place('store', data.store, 'store', data.store?.name);
        place('home', data.destination, 'home', 'Adresse de livraison');
        place('courier', data.courier, 'courier', 'Votre livreur');

        // Tracé en pointillés du livreur vers sa prochaine étape.
        const target = data.stage === 'to_store' || data.stage === 'at_store' ? data.store : data.destination;
        layers.current.route?.remove();
        delete layers.current.route;
        if (data.courier && target && data.stage !== 'arrived' && data.stage !== 'at_store') {
            layers.current.route = L.polyline(
                [
                    [data.courier.lat, data.courier.lng],
                    [target.lat, target.lng],
                ],
                { color: '#1E3A8A', weight: 3, opacity: 0.7, dashArray: '2 8', lineCap: 'round' },
            ).addTo(current);
        }

        // Cadrage : à la première position et à chaque changement d'étape, puis seulement si le
        // livreur sort de l'écran (sinon l'utilisateur garde la main sur le zoom).
        current.invalidateSize();
        const points = [data.courier, target, data.store, data.destination].filter(Boolean).map((p) => [p.lat, p.lng]);
        const fit = (animate) => {
            if (points.length > 1) {
                current.fitBounds(points, { padding: [48, 48], maxZoom: 16, animate });
            } else if (points.length === 1) {
                current.setView(points[0], 15, { animate });
            }
        };
        fitAll.current = () => fit(false);

        const key = `${data.stage}:${points.length}`;
        const courierOut = data.courier && !current.getBounds().contains([data.courier.lat, data.courier.lng]);
        if (fitted.current !== key || courierOut) {
            const first = fitted.current !== key;
            fitted.current = key;
            fit(!first);
        }
    }, [data]);

    if (!data?.active) {
        return null;
    }

    const courier = data.courier;

    return (
        <Card padding="none" className="overflow-hidden">
            <div className="px-5 pt-5">
                <CardHeader title={title} description={description} />
                <p aria-live="polite" className="mt-3 text-base font-semibold text-secondary-900">
                    {data.message}
                </p>
                {data.eta_minutes !== null && (
                    <p className="mt-1 flex flex-wrap items-center gap-x-4 text-sm text-gray-600">
                        <span>
                            Arrivée estimée : <strong className="text-gray-900">{data.eta_minutes} min</strong>
                        </span>
                        <span>
                            Distance : <strong className="text-gray-900">{data.distance_km.toLocaleString('fr-FR')} km</strong>
                        </span>
                    </p>
                )}
            </div>

            <div
                ref={element}
                role="region"
                aria-label="Carte du suivi de livraison"
                className="relative z-0 mt-4 h-64 w-full bg-gray-100 sm:h-80"
            />

            <ul className="flex flex-wrap gap-x-5 gap-y-1 px-5 py-3 text-xs text-gray-600" aria-label="Légende de la carte">
                <li className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded-full bg-primary-500" aria-hidden="true" /> Votre livreur
                    {courier && !courier.fresh && <span className="text-warning-700">(position ancienne)</span>}
                </li>
                <li className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded-full bg-secondary" aria-hidden="true" /> Commerce
                </li>
                {data.destination && (
                    <li className="flex items-center gap-1.5">
                        <span className="h-3 w-3 rounded-full bg-accent" aria-hidden="true" /> Votre adresse (quartier)
                    </li>
                )}
            </ul>
        </Card>
    );
}
