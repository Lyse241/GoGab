import LazyImage from '@/Components/LazyImage';
import QuantityStepper from '@/Components/QuantityStepper';
import { useCart } from '@/Contexts/CartContext';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatPrice, imageUrl } from '@/utils/format';
import { Head, Link } from '@inertiajs/react';

function CartLine({ item, onQuantityChange, onRemove }) {
    return (
        <li className="flex gap-3 py-4">
            <LazyImage
                src={imageUrl(item.image)}
                alt={item.name}
                className="h-20 w-20 shrink-0 rounded-lg"
            />

            <div className="flex min-w-0 flex-1 flex-col">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <h3 className="font-semibold text-gray-900">{item.name}</h3>
                        <p className="text-sm text-gray-500">
                            {formatPrice(item.price)} l'unité
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onRemove}
                        aria-label={`Supprimer ${item.name} du panier`}
                        className="-mr-1 rounded-full p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
                    >
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            strokeWidth="1.8"
                            aria-hidden="true"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                            />
                        </svg>
                    </button>
                </div>

                <div className="mt-auto flex items-center justify-between pt-2">
                    <QuantityStepper
                        quantity={item.quantity}
                        label={item.name}
                        onChange={onQuantityChange}
                    />
                    <p className="font-semibold text-gray-900">
                        {formatPrice(item.price * item.quantity)}
                    </p>
                </div>
            </div>
        </li>
    );
}

export default function Index() {
    const cart = useCart();

    if (cart.items.length === 0) {
        return (
            <PublicLayout>
                <Head title="Panier" />

                <div className="mx-auto mt-16 max-w-md text-center">
                    <p className="text-5xl" aria-hidden="true">
                        🛍️
                    </p>
                    <h1 className="mt-4 text-xl font-bold text-gray-900">
                        Votre panier est vide
                    </h1>
                    <p className="mt-2 text-gray-600">
                        Parcourez les boutiques de Libreville et ajoutez vos
                        premiers articles.
                    </p>
                    <Link
                        href={route('home')}
                        className="mt-6 inline-block rounded-full bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-700"
                    >
                        Voir les boutiques
                    </Link>
                </div>
            </PublicLayout>
        );
    }

    return (
        <PublicLayout>
            <Head title="Panier" />

            <div className="mx-auto max-w-2xl">
                <div className="mt-6 flex items-baseline justify-between gap-2">
                    <h1 className="text-2xl font-bold text-secondary">Mon panier</h1>
                    <button
                        type="button"
                        onClick={cart.clearCart}
                        className="text-sm font-medium text-red-600 hover:underline"
                    >
                        Vider le panier
                    </button>
                </div>
                <p className="mt-1 text-sm text-gray-600">
                    Boutique :{' '}
                    <Link
                        href={route('stores.show', cart.store.id)}
                        className="font-medium text-secondary hover:underline"
                    >
                        {cart.store.name}
                    </Link>
                </p>

                <ul className="mt-4 divide-y divide-gray-200 rounded-xl bg-white px-4 shadow-sm ring-1 ring-gray-200">
                    {cart.items.map((item) => (
                        <CartLine
                            key={item.product_id}
                            item={item}
                            onQuantityChange={(quantity) =>
                                cart.updateQuantity(item.product_id, quantity)
                            }
                            onRemove={() => cart.removeItem(item.product_id)}
                        />
                    ))}
                </ul>

                <div className="mt-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center justify-between">
                        <span className="text-gray-600">
                            Total ({cart.itemCount} article
                            {cart.itemCount > 1 ? 's' : ''})
                        </span>
                        <span className="text-xl font-bold text-gray-900">
                            {formatPrice(cart.total)}
                        </span>
                    </div>

                    <Link
                        href={route('checkout')}
                        className="mt-4 block w-full rounded-full bg-primary-600 py-3 text-center font-semibold text-white hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        Passer commande
                    </Link>
                    <Link
                        href={route('stores.show', cart.store.id)}
                        className="mt-2 block text-center text-sm font-medium text-secondary hover:underline"
                    >
                        Continuer mes achats
                    </Link>
                </div>
            </div>
        </PublicLayout>
    );
}
