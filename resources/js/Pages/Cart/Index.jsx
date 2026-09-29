import CartPanel from '@/Components/Cart/CartPanel';
import { StoreThumb } from '@/Components/Cart/CartList';
import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import { useCart } from '@/Contexts/CartContext';
import PublicLayout from '@/Layouts/PublicLayout';
import { cn } from '@/utils/cn';
import { Head, Link } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import { useEffect } from 'react';

/**
 * Tous les paniers en cours, un bloc par commerce (chacun se commande séparément).
 * `openStore` (?store=) : panier mis en avant, par exemple au retour de la connexion.
 */
export default function Index({ openStore }) {
    const { carts } = useCart();

    // Le panier demandé d'abord.
    const ordered = openStore
        ? [...carts].sort((a, b) => (b.store.id === openStore) - (a.store.id === openStore))
        : carts;

    useEffect(() => {
        if (openStore) {
            document.getElementById(`cart-${openStore}`)?.scrollIntoView({ block: 'start' });
        }
    }, [openStore]);

    return (
        <PublicLayout>
            <Head title="Mes paniers" />

            <div className="mx-auto max-w-2xl pt-6">
                <h1 className="text-2xl font-bold text-secondary-900">Mes paniers</h1>
                {carts.length > 1 && (
                    <p className="mt-1 text-sm text-gray-600">
                        {carts.length} commerces · un panier par commerce, chacun se commande séparément.
                    </p>
                )}

                {carts.length === 0 ? (
                    <EmptyState
                        className="mt-6"
                        icon={ShoppingBag}
                        title="Votre panier est vide"
                        description="Parcourez les commerces de Libreville et ajoutez vos premiers articles."
                        action={<Button href={route('home')}>Voir les commerces</Button>}
                    />
                ) : (
                    <div className="mt-5 space-y-5">
                        {ordered.map((cart) => (
                            <Card
                                key={cart.store.id}
                                id={`cart-${cart.store.id}`}
                                as="section"
                                aria-labelledby={`cart-title-${cart.store.id}`}
                                className={cn('scroll-mt-[calc(var(--header-h,4rem)+1rem)]', cart.store.id === openStore && 'ring-2 ring-primary-200')}
                            >
                                <Link
                                    href={route('stores.show', cart.store.id)}
                                    className="mb-2 flex items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                >
                                    <StoreThumb store={cart.store} className="h-11 w-11" />
                                    <h2 id={`cart-title-${cart.store.id}`} className="truncate text-lg font-bold text-secondary-900">
                                        {cart.store.name}
                                    </h2>
                                </Link>
                                <CartPanel cart={cart} />
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}
