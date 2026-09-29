import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import { useCart } from '@/Contexts/CartContext';
import { useState } from 'react';

/**
 * Ajout au panier depuis une liste de produits. Une commande ne concerne qu'un commerce :
 * ajouter un produit d'un autre commerce demande confirmation (le panier actuel est vidé).
 *
 * Retourne { add(product, store), dialog } : `dialog` est à placer dans la page.
 */
export default function useAddToCart() {
    const cart = useCart();
    const [pending, setPending] = useState(null); // { product, store }
    const [open, setOpen] = useState(false);

    const add = (product, store) => {
        if (cart.isFromOtherStore(store.id)) {
            setPending({ product, store });
            setOpen(true);
            return;
        }

        cart.addItem(product, store);
    };

    const confirm = () => {
        cart.addItem(pending.product, pending.store);
        setOpen(false);
    };

    const dialog = (
        <ConfirmDialog
            open={open}
            onClose={() => setOpen(false)}
            onConfirm={confirm}
            title="Commencer un nouveau panier ?"
            confirmLabel="Vider et ajouter"
            cancelLabel="Garder mon panier"
        >
            <p className="mt-1.5 text-sm text-gray-600">
                Votre panier contient des articles de <strong>{cart.store?.name}</strong>. Une commande ne peut
                concerner qu’un seul commerce : ajouter ce produit{pending ? ` de ${pending.store.name}` : ''} videra le
                panier actuel.
            </p>
        </ConfirmDialog>
    );

    return { add, dialog };
}
