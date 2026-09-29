import { useCart } from '@/Contexts/CartContext';
import { cn } from '@/utils/cn';
import { Link, usePage } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';

/**
 * Icône panier du header avec le nombre d'articles.
 * Masquée pour les rôles qui ne passent pas de commande (admin, livreur, entreprise).
 */
export default function CartButton({ className }) {
    const { role } = usePage().props.auth;
    const { itemCount } = useCart();

    if (role && role !== 'client') {
        return null;
    }

    return (
        <Link
            href={route('cart')}
            aria-label={`Panier, ${itemCount} article${itemCount > 1 ? 's' : ''}`}
            className={cn(
                'relative inline-flex h-11 w-11 items-center justify-center rounded-full text-gray-700 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                className,
            )}
        >
            <ShoppingBag className="h-6 w-6" aria-hidden="true" />
            {itemCount > 0 && (
                <span className="absolute right-1 top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary-600 px-1 text-[11px] font-bold leading-none text-white ring-2 ring-white">
                    {itemCount > 99 ? '99+' : itemCount}
                </span>
            )}
        </Link>
    );
}
