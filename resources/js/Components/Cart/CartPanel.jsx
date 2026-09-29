import LazyImage from '@/Components/LazyImage';
import QuantityStepper from '@/Components/QuantityStepper';
import Button from '@/Components/UI/Button';
import { useCart } from '@/Contexts/CartContext';
import useStoreStatus from '@/Hooks/useStoreStatus';
import { cartTotals } from '@/utils/cartTotals';
import { cn } from '@/utils/cn';
import { formatFCFA, imageUrl } from '@/utils/format';
import { Link, usePage } from '@inertiajs/react';
import { CircleAlert, Clock, Hourglass, Trash2 } from 'lucide-react';
import { useState } from 'react';

/**
 * « chez Le Braisé », mais « chez Maman Ngoye » pour « Chez Maman Ngoye » (pas de « chez Chez »).
 */
export function atStore(name) {
    return /^chez\s/i.test(name) ? `chez ${name.slice(5)}` : `chez ${name}`;
}

function CartLine({ item, unavailable, onQuantityChange, onRemove }) {
    return (
        <li className="flex gap-3 py-3">
            <LazyImage
                src={imageUrl(item.image)}
                alt=""
                className={cn('h-16 w-16 shrink-0 rounded-xl', unavailable && 'opacity-50 grayscale')}
            />
            <div className="flex min-w-0 flex-1 flex-col">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className={cn('font-semibold leading-snug', unavailable ? 'text-gray-500' : 'text-gray-900')}>{item.name}</p>
                        {unavailable ? (
                            <p className="text-sm font-semibold text-danger-700">Plus disponible</p>
                        ) : (
                            <p className="text-sm text-gray-500">{formatFCFA(item.price)} l’unité</p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={onRemove}
                        aria-label={`Supprimer ${item.name} du panier`}
                        className="-mr-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-400 hover:bg-danger-50 hover:text-danger-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger"
                    >
                        <Trash2 className="h-4 w-4" aria-hidden="true" />
                    </button>
                </div>
                <div className="mt-1.5 flex items-center justify-between gap-2">
                    {unavailable ? (
                        <span />
                    ) : (
                        <QuantityStepper quantity={item.quantity} label={item.name} onChange={onQuantityChange} />
                    )}
                    <p className={cn('font-semibold', unavailable ? 'text-gray-400 line-through' : 'text-gray-900')}>
                        {formatFCFA(item.price * item.quantity)}
                    </p>
                </div>
            </div>
        </li>
    );
}

/**
 * Contenu du panier d'UN commerce (tiroir et page /cart) : lignes, sous-total, « Commander chez … »,
 * « Vider ce panier ». À l'affichage, le serveur est interrogé : commerce ouvert ? produits
 * encore disponibles ? (GET /stores/{id}/status).
 *
 * - onNavigate : appelé avant de quitter la page (ex. fermer le tiroir)
 */
export default function CartPanel({ cart, onNavigate, className }) {
    const { auth } = usePage().props;
    const { updateQuantity, removeItem, removeItems, clearCart } = useCart();
    const [confirmClear, setConfirmClear] = useState(false);
    const storeId = cart.store.id;

    const { status, loading } = useStoreStatus(storeId, cart.items.map((item) => item.product_id));
    const checking = loading && status === null;
    const closed = status !== null && !status.is_open_now;
    const unavailableIds = new Set(status?.unavailable_product_ids ?? []);
    const unavailableItems = cart.items.filter((item) => unavailableIds.has(item.product_id));
    const { total, count } = cartTotals(cart.items, unavailableIds);

    const user = auth.user;
    const notClient = user && user.role !== 'client';
    const notApproved = user && user.role === 'client' && user.account_status !== 'approved';
    const blocked = checking || closed || notClient || notApproved || unavailableItems.length > 0 || count === 0;

    const orderLabel = `Commander ${atStore(cart.store.name)}`;
    // Visiteur : connexion, puis retour sur ce panier. Client : checkout de CE commerce uniquement.
    const orderHref = user ? route('checkout', storeId) : route('cart.login', storeId);

    return (
        <div className={cn('flex flex-col', className)}>
            <div className="space-y-3">
                {closed && (
                    <div role="status" className="flex items-start gap-3 rounded-2xl border border-accent-300 bg-accent-50 p-3 text-sm">
                        <Clock className="mt-0.5 h-5 w-5 shrink-0 text-accent-700" aria-hidden="true" />
                        <div>
                            <p className="font-semibold text-secondary-900">{status.status_label}</p>
                            <p className="mt-0.5 text-gray-700">Votre panier est conservé : vous pourrez commander à la réouverture.</p>
                        </div>
                    </div>
                )}

                {unavailableItems.length > 0 && (
                    <div role="alert" className="flex items-start gap-3 rounded-2xl border border-danger-200 bg-danger-50 p-3 text-sm">
                        <CircleAlert className="mt-0.5 h-5 w-5 shrink-0 text-danger-600" aria-hidden="true" />
                        <div>
                            <p className="font-semibold text-danger-800">
                                {unavailableItems.length > 1
                                    ? `${unavailableItems.length} articles ne sont plus disponibles`
                                    : `« ${unavailableItems[0].name} » n’est plus disponible`}
                            </p>
                            <button
                                type="button"
                                onClick={() => removeItems(storeId, unavailableItems.map((item) => item.product_id))}
                                className="mt-1 font-semibold text-danger-700 underline"
                            >
                                {unavailableItems.length > 1 ? 'Les retirer du panier' : 'Le retirer du panier'}
                            </button>
                        </div>
                    </div>
                )}

                {notApproved && (
                    <div role="status" className="flex items-start gap-3 rounded-2xl border border-accent-300 bg-accent-50 p-3 text-sm">
                        <Hourglass className="mt-0.5 h-5 w-5 shrink-0 text-accent-700" aria-hidden="true" />
                        <p className="text-gray-700">
                            <span className="font-semibold text-secondary-900">Compte en cours de validation.</span> Votre panier est
                            conservé : vous pourrez commander dès sa validation.
                        </p>
                    </div>
                )}

                {notClient && (
                    <p role="status" className="rounded-2xl bg-gray-100 p-3 text-sm text-gray-700">
                        Seul un compte client peut commander. Connectez-vous avec un compte client pour valider ce panier.
                    </p>
                )}
            </div>

            <ul className="mt-1 divide-y divide-gray-100">
                {cart.items.map((item) => (
                    <CartLine
                        key={item.product_id}
                        item={item}
                        unavailable={unavailableIds.has(item.product_id)}
                        onQuantityChange={(quantity) => updateQuantity(storeId, item.product_id, quantity)}
                        onRemove={() => removeItem(storeId, item.product_id)}
                    />
                ))}
            </ul>

            <div className="mt-2 border-t border-gray-100 pt-4">
                <div className="flex items-center justify-between">
                    <span className="text-gray-600">
                        Sous-total ({count} article{count > 1 ? 's' : ''})
                    </span>
                    <span className="text-xl font-bold text-gray-900">{formatFCFA(total)}</span>
                </div>
                <p className="mt-1 text-xs text-gray-500">Frais de livraison calculés à l’étape suivante.</p>

                {blocked ? (
                    <Button fullWidth size="lg" className="mt-4" disabled loading={checking}>
                        {checking ? 'Vérification…' : closed ? 'Commerce fermé' : orderLabel}
                    </Button>
                ) : (
                    <Button fullWidth size="lg" className="mt-4" href={orderHref} onClick={onNavigate}>
                        {orderLabel}
                    </Button>
                )}
                {!user && !blocked && (
                    <p className="mt-2 text-center text-xs text-gray-500">Vous serez invité à vous connecter, puis ramené à ce panier.</p>
                )}

                <div className="mt-3 flex min-h-tap items-center justify-center gap-3 text-sm">
                    {confirmClear ? (
                        <>
                            <span className="text-gray-600">Vider ce panier ?</span>
                            <button type="button" onClick={() => clearCart(storeId)} className="font-semibold text-danger-600 underline">
                                Oui, vider
                            </button>
                            <button type="button" onClick={() => setConfirmClear(false)} className="font-semibold text-gray-600 underline">
                                Annuler
                            </button>
                        </>
                    ) : (
                        <button type="button" onClick={() => setConfirmClear(true)} className="font-medium text-danger-600 hover:underline">
                            Vider ce panier
                        </button>
                    )}
                </div>
                <Link
                    href={route('stores.show', storeId)}
                    onClick={onNavigate}
                    className="block text-center text-sm font-medium text-secondary hover:underline"
                >
                    Continuer mes achats {atStore(cart.store.name)}
                </Link>
            </div>
        </div>
    );
}
