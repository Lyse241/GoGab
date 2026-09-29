import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

const STORAGE_KEY = 'gogab_neighborhood';

/**
 * Quartier de livraison choisi dans le header (identifiant), mémorisé dans le navigateur.
 * Sert à préremplir la commande ; la liste des quartiers vient de la prop partagée `neighborhoods`.
 */
const NeighborhoodContext = createContext(null);

function load() {
    try {
        const id = Number(window.localStorage.getItem(STORAGE_KEY));

        return Number.isInteger(id) && id > 0 ? id : null;
    } catch {
        return null;
    }
}

export function NeighborhoodProvider({ children }) {
    const [neighborhoodId, setNeighborhoodId] = useState(load);

    useEffect(() => {
        try {
            if (neighborhoodId) {
                window.localStorage.setItem(STORAGE_KEY, String(neighborhoodId));
            } else {
                window.localStorage.removeItem(STORAGE_KEY);
            }
        } catch {
            // Stockage indisponible : le choix reste valable pour la session en cours.
        }
    }, [neighborhoodId]);

    const choose = useCallback((id) => setNeighborhoodId(id ? Number(id) : null), []);

    const value = useMemo(() => ({ neighborhoodId, setNeighborhoodId: choose }), [neighborhoodId, choose]);

    return <NeighborhoodContext.Provider value={value}>{children}</NeighborhoodContext.Provider>;
}

export function useNeighborhood() {
    const context = useContext(NeighborhoodContext);

    if (!context) {
        throw new Error('useNeighborhood doit être utilisé dans un <NeighborhoodProvider>.');
    }

    return context;
}
