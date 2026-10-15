import Button from '@/Components/UI/Button';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { LocateFixed, MapPinOff } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const STORE_PATH =
    '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/>';

const storeIcon = () =>
    L.divIcon({
        className: 'gogab-pin',
        html: `<span style="background:#1E3A8A;color:#ffffff"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${STORE_PATH}</svg></span>`,
        iconSize: [40, 40],
        iconAnchor: [20, 20],
    });

const LIBREVILLE = [0.4162, 9.4673];

/**
 * Épingle exacte du commerce sur une carte : un clic (ou un glisser du repère) place la boutique.
 * Les livreurs et les clients voient alors le commerce au bon endroit, et l'alerte « votre livreur
 * arrive » se déclenche à 150 m au lieu de 600 m. Sans épingle, le commerce est placé au centre de
 * son quartier.
 *
 * - latitude / longitude : épingle actuelle (null = aucune)
 * - center : [lat, lng] de départ de la carte (centre du quartier choisi)
 * - onChange(lat, lng) : nouvelle position, ou (null, null) pour retirer l'épingle
 */
export default function StorePinPicker({ latitude, longitude, center, onChange, error }) {
    const element = useRef(null);
    const map = useRef(null);
    const marker = useRef(null);
    const callback = useRef(onChange);
    callback.current = onChange;
    const [message, setMessage] = useState(null);
    const hasPin = latitude !== null && latitude !== '' && longitude !== null && longitude !== '';

    // Création de la carte (une fois).
    useEffect(() => {
        if (!element.current || map.current) return undefined;

        const start = hasPin ? [Number(latitude), Number(longitude)] : (center ?? LIBREVILLE);
        map.current = L.map(element.current, { scrollWheelZoom: false }).setView(start, hasPin ? 17 : 15);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
        }).addTo(map.current);

        map.current.on('click', (event) => callback.current(round(event.latlng.lat), round(event.latlng.lng)));

        const observer = new ResizeObserver(() => map.current?.invalidateSize());
        observer.observe(element.current);

        return () => {
            observer.disconnect();
            map.current.remove();
            map.current = null;
            marker.current = null;
        };
        // La carte n'est créée qu'une fois : les changements suivants passent par l'effet ci-dessous.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Repère glissable, synchronisé avec le formulaire.
    useEffect(() => {
        const current = map.current;
        if (!current) return;

        if (!hasPin) {
            marker.current?.remove();
            marker.current = null;
            return;
        }

        const latlng = [Number(latitude), Number(longitude)];
        if (marker.current) {
            marker.current.setLatLng(latlng);
        } else {
            marker.current = L.marker(latlng, { icon: storeIcon(), draggable: true, title: 'Votre commerce' }).addTo(current);
            marker.current.on('dragend', () => {
                const { lat, lng } = marker.current.getLatLng();
                callback.current(round(lat), round(lng));
            });
        }
        if (!current.getBounds().contains(latlng)) current.setView(latlng, current.getZoom());
    }, [hasPin, latitude, longitude]);

    // Le quartier change : la carte se recentre (tant qu'il n'y a pas d'épingle).
    useEffect(() => {
        if (map.current && !hasPin && center) map.current.setView(center, 15);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [center?.[0], center?.[1]]);

    const useMyPosition = () => {
        setMessage(null);
        if (!('geolocation' in navigator) || !window.isSecureContext) {
            setMessage('La localisation demande une connexion sécurisée (https). Cliquez plutôt sur la carte.');
            return;
        }
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                onChange(round(coords.latitude), round(coords.longitude));
                map.current?.setView([coords.latitude, coords.longitude], 18);
            },
            () => setMessage('Position introuvable : autorisez la localisation, ou cliquez sur la carte.'),
            { enableHighAccuracy: true, timeout: 15000 },
        );
    };

    return (
        <div>
            <div
                ref={element}
                role="region"
                aria-label="Carte pour placer votre commerce"
                className="relative z-0 h-72 w-full overflow-hidden rounded-2xl bg-gray-100 ring-1 ring-gray-200"
            />
            <div className="mt-3 flex flex-wrap items-center gap-2">
                <Button type="button" size="sm" variant="outline" icon={LocateFixed} onClick={useMyPosition}>
                    Je suis dans mon commerce
                </Button>
                {hasPin && (
                    <Button type="button" size="sm" variant="ghost" icon={MapPinOff} onClick={() => onChange(null, null)}>
                        Retirer l’épingle
                    </Button>
                )}
            </div>
            <p className="mt-2 text-sm text-gray-600" aria-live="polite">
                {hasPin
                    ? 'Épingle placée. Glissez le repère pour l’affiner, puis enregistrez.'
                    : 'Cliquez sur la carte à l’emplacement exact de votre commerce. Sans épingle, il est placé au centre de votre quartier.'}
            </p>
            {message && <p className="mt-1 text-sm text-warning-800">{message}</p>}
            {error && <p className="mt-1 text-sm text-danger-600">{error}</p>}
        </div>
    );
}

const round = (value) => Math.round(value * 1e7) / 1e7;
