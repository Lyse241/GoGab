import { useCart } from '@/Contexts/CartContext';
import { Link, usePage } from '@inertiajs/react';

/**
 * Icône panier du header avec le nombre d'articles.
 * Masquée pour les admins et livreurs, qui ne passent pas de commande.
 */
export default function CartButton({ className = '' }) {
    const { role } = usePage().props.auth;
    const { itemCount } = useCart();

    if (role === 'admin' || role === 'delivery') {
        return null;
    }

    return (
        <Link
            href={route('cart')}
            aria-label={`Panier, ${itemCount} article${itemCount > 1 ? 's' : ''}`}
            className={`relative inline-flex h-10 w-10 items-center justify-center rounded-full text-gray-700 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 ${className}`}
        >
            <svg
                className="h-6 w-6"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                strokeWidth="1.8"
                aria-hidden="true"
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z"
                />
            </svg>
            {itemCount > 0 && (
                <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-emerald-600 px-1 text-[11px] font-bold leading-none text-white">
                    {itemCount > 99 ? '99+' : itemCount}
                </span>
            )}
        </Link>
    );
}
