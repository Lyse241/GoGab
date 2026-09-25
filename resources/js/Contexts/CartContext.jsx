import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useReducer,
} from 'react';

const STORAGE_KEY = 'gogab_cart';
export const MAX_QUANTITY = 99;

/**
 * Forme du panier :
 * {
 *   store: { id, name } | null,   // une seule boutique à la fois
 *   items: [{ product_id, name, price, image, quantity }]
 * }
 * Les infos produit sont une copie pour l'affichage : le serveur recalculera
 * les prix au moment de la commande.
 */
const emptyCart = { store: null, items: [] };

const clampQuantity = (quantity) =>
    Math.min(MAX_QUANTITY, Math.max(0, Math.floor(Number(quantity) || 0)));

export function cartReducer(state, action) {
    switch (action.type) {
        case 'add': {
            const { product, store, quantity } = action;
            // Boutique différente : on repart d'un panier vide (confirmation gérée par l'UI).
            const base =
                state.store && state.store.id !== store.id ? emptyCart : state;
            const existing = base.items.find(
                (item) => item.product_id === product.id,
            );

            const items = existing
                ? base.items.map((item) =>
                      item.product_id === product.id
                          ? {
                                ...item,
                                quantity: clampQuantity(item.quantity + quantity),
                            }
                          : item,
                  )
                : [
                      ...base.items,
                      {
                          product_id: product.id,
                          name: product.name,
                          price: Number(product.price),
                          image: product.image ?? null,
                          quantity: clampQuantity(quantity),
                      },
                  ];

            return { store: { id: store.id, name: store.name }, items };
        }

        case 'update': {
            const quantity = clampQuantity(action.quantity);
            const items =
                quantity === 0
                    ? state.items.filter((item) => item.product_id !== action.productId)
                    : state.items.map((item) =>
                          item.product_id === action.productId
                              ? { ...item, quantity }
                              : item,
                      );

            return items.length ? { ...state, items } : emptyCart;
        }

        case 'remove': {
            const items = state.items.filter(
                (item) => item.product_id !== action.productId,
            );

            return items.length ? { ...state, items } : emptyCart;
        }

        case 'clear':
            return emptyCart;

        case 'replace':
            return action.cart;

        default:
            return state;
    }
}

/**
 * Lit le panier sauvegardé ; toute donnée invalide ou corrompue donne un panier vide.
 */
function loadCart() {
    try {
        const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY));

        if (
            saved &&
            Array.isArray(saved.items) &&
            saved.items.length > 0 &&
            saved.store?.id
        ) {
            const items = saved.items
                .filter((item) => item.product_id && clampQuantity(item.quantity) > 0)
                .map((item) => ({
                    ...item,
                    price: Number(item.price) || 0,
                    quantity: clampQuantity(item.quantity),
                }));

            return items.length ? { store: saved.store, items } : emptyCart;
        }
    } catch {
        // localStorage indisponible (navigation privée…) ou JSON invalide.
    }

    return emptyCart;
}

const CartContext = createContext(null);

export function CartProvider({ children }) {
    const [cart, dispatch] = useReducer(cartReducer, undefined, loadCart);

    // Sauvegarde à chaque modification.
    useEffect(() => {
        try {
            if (cart.items.length) {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
            } else {
                window.localStorage.removeItem(STORAGE_KEY);
            }
        } catch {
            // Stockage plein ou bloqué : le panier reste utilisable en mémoire.
        }
    }, [cart]);

    // Synchronise les onglets ouverts en même temps.
    useEffect(() => {
        const onStorage = (event) => {
            if (event.key === STORAGE_KEY) {
                dispatch({ type: 'replace', cart: loadCart() });
            }
        };

        window.addEventListener('storage', onStorage);

        return () => window.removeEventListener('storage', onStorage);
    }, []);

    const addItem = useCallback(
        (product, store, quantity = 1) =>
            dispatch({ type: 'add', product, store, quantity }),
        [],
    );
    const updateQuantity = useCallback(
        (productId, quantity) => dispatch({ type: 'update', productId, quantity }),
        [],
    );
    const removeItem = useCallback(
        (productId) => dispatch({ type: 'remove', productId }),
        [],
    );
    const clearCart = useCallback(() => dispatch({ type: 'clear' }), []);

    const value = useMemo(() => {
        const total = cart.items.reduce(
            (sum, item) => sum + item.price * item.quantity,
            0,
        );

        return {
            store: cart.store,
            items: cart.items,
            itemCount: cart.items.reduce((sum, item) => sum + item.quantity, 0),
            total: Math.round(total * 100) / 100,
            addItem,
            updateQuantity,
            removeItem,
            clearCart,
            // Vrai si ajouter un produit de cette boutique viderait le panier actuel.
            isFromOtherStore: (storeId) =>
                cart.store !== null && cart.store.id !== storeId,
            quantityOf: (productId) =>
                cart.items.find((item) => item.product_id === productId)
                    ?.quantity ?? 0,
        };
    }, [cart, addItem, updateQuantity, removeItem, clearCart]);

    return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart() {
    const context = useContext(CartContext);

    if (!context) {
        throw new Error('useCart doit être utilisé dans un <CartProvider>.');
    }

    return context;
}
