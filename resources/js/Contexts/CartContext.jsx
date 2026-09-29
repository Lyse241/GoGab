import { createContext, useCallback, useContext, useEffect, useMemo, useReducer, useState } from 'react';

const STORAGE_KEY = 'gogab_carts';
// Ancien format (un seul panier) : repris une fois puis supprimé.
const LEGACY_KEY = 'gogab_cart';
export const MAX_QUANTITY = 99;

/**
 * Paniers de Gogab : UN panier par commerce, jamais mélangés.
 *
 * {
 *   [storeId]: {
 *     store: { id, name, logo },
 *     items: [{ product_id, name, price, image, quantity }],
 *     updated_at: timestamp (ordre d'affichage : le plus récent d'abord)
 *   }
 * }
 *
 * Un produit va toujours dans le panier de SON commerce (créé au besoin) : aucune action ne
 * permet de le placer ailleurs, et ajouter chez un commerce ne touche jamais aux autres paniers.
 * Les infos produit sont une copie pour l'affichage : le serveur recalcule les prix à la commande.
 */
const clampQuantity = (quantity) => Math.min(MAX_QUANTITY, Math.max(0, Math.floor(Number(quantity) || 0)));

/**
 * Remplace les items d'un panier ; un panier vide est supprimé (nettoyage automatique).
 */
function withItems(state, storeId, items) {
    const { [storeId]: current, ...others } = state;

    if (!current || items.length === 0) {
        return others;
    }

    return { ...others, [storeId]: { ...current, items, updated_at: Date.now() } };
}

export function cartsReducer(state, action) {
    switch (action.type) {
        case 'add': {
            const { product, store, quantity } = action;
            // Le panier est toujours celui du commerce du produit.
            const storeId = product.store_id ?? store.id;

            if (product.store_id !== undefined && product.store_id !== store.id) {
                return state;
            }

            const current = state[storeId] ?? { store: null, items: [] };
            const existing = current.items.find((item) => item.product_id === product.id);
            const items = existing
                ? current.items.map((item) =>
                      item.product_id === product.id
                          ? { ...item, quantity: clampQuantity(item.quantity + quantity) }
                          : item,
                  )
                : [
                      ...current.items,
                      {
                          product_id: product.id,
                          name: product.name,
                          price: Number(product.price),
                          image: product.image ?? null,
                          quantity: clampQuantity(quantity),
                      },
                  ];

            return {
                ...state,
                [storeId]: {
                    store: { id: store.id, name: store.name, logo: store.logo ?? null },
                    items,
                    updated_at: Date.now(),
                },
            };
        }

        case 'update': {
            const current = state[action.storeId];
            if (!current) {
                return state;
            }

            const quantity = clampQuantity(action.quantity);
            const items =
                quantity === 0
                    ? current.items.filter((item) => item.product_id !== action.productId)
                    : current.items.map((item) => (item.product_id === action.productId ? { ...item, quantity } : item));

            return withItems(state, action.storeId, items);
        }

        case 'remove': {
            const current = state[action.storeId];

            return current
                ? withItems(state, action.storeId, current.items.filter((item) => !action.productIds.includes(item.product_id)))
                : state;
        }

        case 'clear':
            return withItems(state, action.storeId, []);

        case 'replace':
            return action.carts;

        default:
            return state;
    }
}

/**
 * Nettoie des paniers lus depuis le stockage : données invalides ignorées, paniers vides retirés.
 */
function sanitize(raw) {
    const carts = {};

    Object.values(raw && typeof raw === 'object' ? raw : {}).forEach((cart) => {
        const id = Number(cart?.store?.id);
        if (!id || !Array.isArray(cart.items)) {
            return;
        }

        const items = cart.items
            .filter((item) => item?.product_id && clampQuantity(item.quantity) > 0)
            .map((item) => ({ ...item, price: Number(item.price) || 0, quantity: clampQuantity(item.quantity) }));

        if (items.length > 0) {
            carts[id] = { store: { id, name: cart.store.name ?? '', logo: cart.store.logo ?? null }, items, updated_at: Number(cart.updated_at) || 0 };
        }
    });

    return carts;
}

function loadCarts() {
    try {
        const saved = window.localStorage.getItem(STORAGE_KEY);
        if (saved) {
            return sanitize(JSON.parse(saved));
        }

        // Reprise de l'ancien panier unique.
        const legacy = JSON.parse(window.localStorage.getItem(LEGACY_KEY));
        window.localStorage.removeItem(LEGACY_KEY);

        return legacy?.store ? sanitize({ [legacy.store.id]: legacy }) : {};
    } catch {
        // localStorage indisponible (navigation privée…) ou JSON invalide.
        return {};
    }
}

const subtotalOf = (items) => Math.round(items.reduce((sum, item) => sum + item.price * item.quantity, 0) * 100) / 100;
const countOf = (items) => items.reduce((sum, item) => sum + item.quantity, 0);

const CartContext = createContext(null);

export function CartProvider({ children }) {
    const [carts, dispatch] = useReducer(cartsReducer, undefined, loadCarts);
    // Tiroir du panier : null (fermé), 'list' (tous les paniers) ou l'id d'un commerce.
    const [drawer, setDrawer] = useState(null);

    // Sauvegarde à chaque modification (les paniers vides ont déjà été retirés).
    useEffect(() => {
        try {
            if (Object.keys(carts).length) {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(carts));
            } else {
                window.localStorage.removeItem(STORAGE_KEY);
            }
        } catch {
            // Stockage plein ou bloqué : les paniers restent utilisables en mémoire.
        }
    }, [carts]);

    // Paniers modifiés dans un autre onglet.
    useEffect(() => {
        const onStorage = (event) => {
            if (event.key === STORAGE_KEY) {
                dispatch({ type: 'replace', carts: loadCarts() });
            }
        };
        window.addEventListener('storage', onStorage);

        return () => window.removeEventListener('storage', onStorage);
    }, []);

    const addItem = useCallback((product, store, quantity = 1) => dispatch({ type: 'add', product, store, quantity }), []);
    const updateQuantity = useCallback((storeId, productId, quantity) => dispatch({ type: 'update', storeId, productId, quantity }), []);
    const removeItems = useCallback((storeId, productIds) => dispatch({ type: 'remove', storeId, productIds }), []);
    const clearCart = useCallback((storeId) => dispatch({ type: 'clear', storeId }), []);

    const value = useMemo(() => {
        // Plus récemment modifié d'abord.
        const list = Object.values(carts)
            .sort((a, b) => b.updated_at - a.updated_at)
            .map((cart) => ({ ...cart, count: countOf(cart.items), subtotal: subtotalOf(cart.items) }));

        return {
            carts: list,
            itemCount: list.reduce((sum, cart) => sum + cart.count, 0),
            cartOf: (storeId) => list.find((cart) => cart.store.id === Number(storeId)) ?? null,
            quantityOf: (storeId, productId) =>
                carts[storeId]?.items.find((item) => item.product_id === productId)?.quantity ?? 0,
            addItem,
            updateQuantity,
            removeItem: (storeId, productId) => removeItems(storeId, [productId]),
            removeItems,
            clearCart,
            // Tiroir : un panier, ou la liste (qui ouvre directement l'unique panier s'il n'y en a qu'un).
            drawer,
            openCart: (storeId) => setDrawer(Number(storeId)),
            openCarts: () => setDrawer(list.length === 1 ? list[0].store.id : 'list'),
            closeDrawer: () => setDrawer(null),
        };
    }, [carts, drawer, addItem, updateQuantity, removeItems, clearCart]);

    return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart() {
    const context = useContext(CartContext);

    if (!context) {
        throw new Error('useCart doit être utilisé dans un <CartProvider>.');
    }

    return context;
}
