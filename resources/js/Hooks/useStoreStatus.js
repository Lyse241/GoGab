import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';

/**
 * État d'ouverture d'un commerce, demandé au serveur (GET /stores/{id}/status) :
 * au montage, puis au retour sur l'onglet. Le navigateur ne décide jamais seul.
 *
 * - productIds : produits du panier, pour savoir lesquels ne sont plus disponibles
 *   (indisponibles ou supprimés) → status.unavailable_product_ids
 *
 * Retourne { status: { is_open_now, status_label, status_detail, unavailable_product_ids } | null, loading, refresh }.
 * En cas d'erreur réseau, status reste null : la commande n'est pas bloquée côté interface
 * (le serveur refusera de toute façon une commande pour un commerce fermé).
 */
export default function useStoreStatus(storeId, productIds = []) {
    // Clé stable : la vérification n'est relancée que si la liste des produits change.
    const products = [...productIds].sort((a, b) => a - b).join(',');
    const [status, setStatus] = useState(null);
    const [loading, setLoading] = useState(Boolean(storeId));

    const refresh = useCallback(async () => {
        if (!storeId) {
            setStatus(null);
            setLoading(false);
            return;
        }

        try {
            const { data } = await axios.get(route('stores.status', storeId), { params: products ? { products } : {} });
            setStatus(data);
        } catch {
            setStatus(null);
        } finally {
            setLoading(false);
        }
    }, [storeId, products]);

    useEffect(() => {
        setLoading(Boolean(storeId));
        refresh();

        const onVisible = () => {
            if (!document.hidden) {
                refresh();
            }
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => document.removeEventListener('visibilitychange', onVisible);
    }, [refresh, storeId]);

    return { status, loading, refresh };
}
