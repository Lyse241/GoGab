import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Vrai pendant le chargement d'une autre vue de la même liste (onglet, filtre, recherche, page) :
 * visite GET vers le même chemin avec d'autres paramètres. Les rafraîchissements automatiques
 * (usePoll recharge l'URL courante) ne déclenchent rien. Délai de 200 ms : pas de squelette qui
 * clignote pour une réponse rapide.
 */
export default function useListLoading(delay = 200) {
    const [loading, setLoading] = useState(false);
    const timer = useRef(null);

    useEffect(() => {
        const offStart = router.on('start', (event) => {
            const visit = event.detail.visit;
            const target = new URL(visit.url);
            const current = new URL(window.location.href);
            const otherView = visit.method === 'get' && target.pathname === current.pathname && target.search !== current.search;

            if (otherView) {
                clearTimeout(timer.current);
                timer.current = setTimeout(() => setLoading(true), delay);
            }
        });
        const offFinish = router.on('finish', () => {
            clearTimeout(timer.current);
            setLoading(false);
        });

        return () => {
            clearTimeout(timer.current);
            offStart();
            offFinish();
        };
    }, [delay]);

    return loading;
}
