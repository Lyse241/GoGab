import Modal from '@/Components/Modal';
import { MAX_QUANTITY, useCart } from '@/Contexts/CartContext';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatPrice, imageUrl } from '@/utils/format';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

function QuantityStepper({ quantity, onChange, label }) {
    const buttonClass =
        'flex h-8 w-8 items-center justify-center rounded-full text-lg font-bold text-emerald-700 hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:opacity-40';

    return (
        <div className="flex items-center gap-1 rounded-full bg-emerald-50 p-0.5">
            <button
                type="button"
                className={buttonClass}
                onClick={() => onChange(quantity - 1)}
                aria-label={`Retirer un ${label}`}
            >
                −
            </button>
            <span className="w-6 text-center text-sm font-semibold" aria-live="polite">
                {quantity}
            </span>
            <button
                type="button"
                className={buttonClass}
                onClick={() => onChange(quantity + 1)}
                disabled={quantity >= MAX_QUANTITY}
                aria-label={`Ajouter un ${label}`}
            >
                +
            </button>
        </div>
    );
}

function ProductCard({ product, quantity, onAdd, onQuantityChange }) {
    return (
        <article className="flex gap-4 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-200">
            <div className="h-24 w-24 shrink-0 overflow-hidden rounded-lg bg-gray-200">
                {product.image && (
                    <img
                        src={imageUrl(product.image)}
                        alt={product.name}
                        loading="lazy"
                        className="h-full w-full object-cover"
                    />
                )}
            </div>
            <div className="flex min-w-0 flex-1 flex-col">
                <h3 className="font-semibold text-gray-900">{product.name}</h3>
                {product.description && (
                    <p className="mt-1 line-clamp-2 text-sm text-gray-500">
                        {product.description}
                    </p>
                )}
                <div className="mt-auto flex items-center justify-between gap-2 pt-2">
                    <p className="font-bold text-emerald-700">
                        {formatPrice(product.price)}
                    </p>
                    {quantity > 0 ? (
                        <QuantityStepper
                            quantity={quantity}
                            label={product.name}
                            onChange={onQuantityChange}
                        />
                    ) : (
                        <button
                            type="button"
                            onClick={onAdd}
                            className="rounded-full bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
                        >
                            Ajouter au panier
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

    return (
        <PublicLayout>
            <Head title={store.name} />

            <Link
                href={route('home')}
                className="mt-4 inline-block text-sm font-medium text-emerald-700 hover:underline"
            >
                ← Toutes les boutiques
            </Link>

            <section className="relative mt-3 overflow-hidden rounded-xl bg-gray-800">
                {store.image && (
                    <img
                        src={imageUrl(store.image)}
                        alt=""
                        className="h-40 w-full object-cover opacity-60 sm:h-56"
                    />
                )}
                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-4 sm:p-6">
                    <span className="rounded bg-emerald-600 px-2 py-0.5 text-xs font-medium text-white">
                        {store.category}
                    </span>
                    <h1 className="mt-2 text-2xl font-bold text-white sm:text-3xl">
                        {store.name}
                    </h1>
                </div>
            </section>

            <h2 className="mb-3 mt-6 text-lg font-semibold text-gray-800">
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
                                    {formatPrice(cart.total)}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={cart.clearCart}
                                className="rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
                            >
                                Vider le panier
                            </button>
                        </div>
                    </div>
                </>
            )}

            <Modal
                show={pendingProduct !== null}
                maxWidth="md"
                onClose={() => setPendingProduct(null)}
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900">
                        Commencer un nouveau panier ?
                    </h2>
                    <p className="mt-2 text-sm text-gray-600">
                        Votre panier contient des articles de{' '}
                        <strong>{cart.store?.name}</strong>. Une commande ne
                        peut concerner qu'une seule boutique : ajouter ce
                        produit videra le panier actuel.
                    </p>
                    <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            onClick={() => setPendingProduct(null)}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Garder mon panier
                        </button>
                        <button
                            type="button"
                            onClick={confirmReplace}
                            className="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                        >
                            Vider et ajouter
                        </button>
                    </div>
                </div>
            </Modal>
        </PublicLayout>
    );
}
