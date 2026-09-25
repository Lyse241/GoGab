import Modal from '@/Components/Modal';
import QuantityStepper from '@/Components/QuantityStepper';
import { useCart } from '@/Contexts/CartContext';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatPrice, imageUrl } from '@/utils/format';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

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
                <div className="mt-auto flex flex-wrap items-center justify-between gap-2 pt-2">
                    <p className="whitespace-nowrap font-bold text-primary-700">
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
                            className="whitespace-nowrap rounded-full bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
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
                className="mt-4 inline-block text-sm font-medium text-secondary hover:underline"
            >
                ← Toutes les boutiques
            </Link>

            <section className="relative mt-3 overflow-hidden rounded-xl bg-secondary">
                {store.image && (
                    <img
                        src={imageUrl(store.image)}
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
                </div>
            </section>

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

            <Modal
                show={pendingProduct !== null}
                maxWidth="md"
                onClose={() => setPendingProduct(null)}
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-secondary">
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
                            className="rounded-full border border-secondary-200 px-5 py-2 text-sm font-semibold text-secondary hover:bg-secondary-50"
                        >
                            Garder mon panier
                        </button>
                        <button
                            type="button"
                            onClick={confirmReplace}
                            className="rounded-full bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700"
                        >
                            Vider et ajouter
                        </button>
                    </div>
                </div>
            </Modal>
        </PublicLayout>
    );
}
