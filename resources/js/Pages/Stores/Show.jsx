import LazyImage from '@/Components/LazyImage';
import { DAYS } from '@/Components/OpeningHoursEditor';
import OpeningStatusBadge from '@/Components/OpeningStatusBadge';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import QuantityStepper from '@/Components/QuantityStepper';
import { useCart } from '@/Contexts/CartContext';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatFCFA, imageUrl } from '@/utils/format';
import { Head, Link } from '@inertiajs/react';
import { Clock, Store as StoreIcon } from 'lucide-react';
import { useState } from 'react';

function ProductCard({ product, quantity, onAdd, onQuantityChange, canOrder }) {
    return (
        <article className="flex gap-4 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-200">
            <LazyImage
                src={imageUrl(product.image)}
                alt={product.name}
                className="h-24 w-24 shrink-0 rounded-lg"
            />
            <div className="flex min-w-0 flex-1 flex-col">
                <h3 className="font-semibold text-gray-900">{product.name}</h3>
                {product.description && (
                    <p className="mt-1 line-clamp-2 text-sm text-gray-500">
                        {product.description}
                    </p>
                )}
                <div className="mt-auto flex flex-wrap items-center justify-between gap-2 pt-2">
                    <p className="whitespace-nowrap font-bold text-primary-700">
                        {formatFCFA(product.price)}
                    </p>
                    {quantity > 0 && canOrder ? (
                        <QuantityStepper
                            quantity={quantity}
                            label={product.name}
                            onChange={onQuantityChange}
                        />
                    ) : (
                        <button
                            type="button"
                            onClick={onAdd}
                            disabled={!canOrder}
                            className="whitespace-nowrap rounded-full bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-500"
                        >
                            {canOrder ? 'Ajouter au panier' : 'Indisponible'}
                        </button>
                    )}
                </div>
            </div>
        </article>
    );
}

export default function Show({ store, products }) {
    const cart = useCart();
    // Produit en attente pendant la confirmation "changer de boutique".
    const [pendingProduct, setPendingProduct] = useState(null);

    const handleAdd = (product) => {
        if (cart.isFromOtherStore(store.id)) {
            setPendingProduct(product);
            return;
        }

        cart.addItem(product, store);
    };

    const confirmReplace = () => {
        cart.addItem(pendingProduct, store);
        setPendingProduct(null);
    };

    const cartIsHere = cart.store?.id === store.id && cart.itemCount > 0;
    // Calculé par le serveur à l'heure de Libreville.
    const canOrder = store.is_open_now;


    return (
        <PublicLayout>
            <Head title={store.name} />

            <Link
                href={route('home')}
                className="mt-4 inline-block text-sm font-medium text-secondary hover:underline"
            >
                ← Toutes les boutiques
            </Link>

            <section className="relative mt-3 overflow-hidden rounded-xl bg-secondary">
                {store.cover_image && (
                    <img
                        src={imageUrl(store.cover_image)}
                        alt=""
                        className="h-40 w-full object-cover opacity-60 sm:h-56"
                    />
                )}
                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-secondary-900/80 to-transparent p-4 sm:p-6">
                    <span className="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-secondary-900">
                        {store.category}
                    </span>
                    <h1 className="mt-2 text-2xl font-bold text-white sm:text-3xl">
                        {store.name}
                    </h1>
                    <OpeningStatusBadge
                        variant="overlay"
                        isOpen={store.is_open_now}
                        detail={store.status_detail}
                        className="mt-2 max-w-full"
                    />
                </div>
            </section>

            {!canOrder && (
                <div
                    role="status"
                    className="mt-4 flex items-start gap-3 rounded-2xl border border-accent-300 bg-accent-50 p-4"
                >
                    <StoreIcon className="mt-0.5 h-5 w-5 shrink-0 text-accent-700" aria-hidden="true" />
                    <div>
                        <p className="font-semibold text-secondary-900">{store.status_label}</p>
                        <p className="mt-0.5 text-sm text-gray-700">
                            Vous pouvez consulter le menu, mais les commandes ne sont pas possibles pour le moment.
                        </p>
                    </div>
                </div>
            )}

            <details className="group mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <summary className="flex cursor-pointer list-none items-center justify-between gap-2 font-semibold text-secondary-900">
                    <span className="inline-flex items-center gap-2">
                        <Clock className="h-5 w-5 text-primary-600" aria-hidden="true" />
                        Horaires d’ouverture
                    </span>
                    <span className="text-sm font-medium text-secondary group-open:hidden">Afficher</span>
                    <span className="hidden text-sm font-medium text-secondary group-open:inline">Masquer</span>
                </summary>
                <ul className="mt-3 space-y-1 text-sm">
                    {store.opening_hours.map((day, index) => (
                        <li
                            key={day.day_of_week}
                            className={`flex justify-between rounded-lg px-2 py-1.5 ${day.day_of_week === store.today ? 'bg-primary-50 font-semibold text-gray-900' : 'text-gray-700'}`}
                        >
                            <span>{DAYS[index]}</span>
                            <span>
                                {day.is_closed || !day.opens_at
                                    ? 'Fermé'
                                    : day.opens_at === day.closes_at
                                      ? '24 h/24'
                                      : `${day.opens_at.replace(':', 'h')} – ${day.closes_at.replace(':', 'h')}`}
                            </span>
                        </li>
                    ))}
                </ul>
            </details>

            <h2 className="mb-3 mt-6 text-lg font-semibold text-secondary">
                Menu ({products.length})
            </h2>

            {products.length === 0 ? (
                <p className="text-gray-500">
                    Cette boutique n'a pas encore de produits.
                </p>
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {products.map((product) => (
                        <ProductCard
                            key={product.id}
                            product={product}
                            quantity={
                                cart.store?.id === store.id
                                    ? cart.quantityOf(product.id)
                                    : 0
                            }
                            onAdd={() => handleAdd(product)}
                            canOrder={canOrder}
                            onQuantityChange={(quantity) =>
                                cart.updateQuantity(product.id, quantity)
                            }
                        />
                    ))}
                </div>
            )}

            {/* Récapitulatif fixé en bas de l'écran (pouce-friendly sur mobile) */}
            {cartIsHere && (
                <>
                    <div className="h-20" aria-hidden="true" />
                    <div className="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white p-3 shadow-[0_-4px_12px_rgba(0,0,0,0.06)]">
                        <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-1 sm:px-6">
                            <div>
                                <p className="text-sm text-gray-600">
                                    Panier · {cart.itemCount} article
                                    {cart.itemCount > 1 ? 's' : ''}
                                </p>
                                <p className="text-lg font-bold text-gray-900">
                                    {formatFCFA(cart.total)}
                                </p>
                            </div>
                            <Link
                                href={route('cart')}
                                className="rounded-full bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                            >
                                Voir le panier
                            </Link>
                        </div>
                    </div>
                </>
            )}

            <ConfirmDialog
                open={pendingProduct !== null}
                onClose={() => setPendingProduct(null)}
                onConfirm={confirmReplace}
                title="Commencer un nouveau panier ?"
                confirmLabel="Vider et ajouter"
                cancelLabel="Garder mon panier"
            >
                <p className="mt-1.5 text-sm text-gray-600">
                    Votre panier contient des articles de{' '}
                    <strong>{cart.store?.name}</strong>. Une commande ne peut
                    concerner qu'une seule boutique : ajouter ce produit videra
                    le panier actuel.
                </p>
            </ConfirmDialog>
        </PublicLayout>
    );
}
