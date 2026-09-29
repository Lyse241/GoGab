import SearchBar from '@/Components/Layout/SearchBar';
import LazyImage from '@/Components/LazyImage';
import OpeningStatusBadge from '@/Components/OpeningStatusBadge';
import ProductCard from '@/Components/ProductCard';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import { useCart } from '@/Contexts/CartContext';
import useAddToCart from '@/Hooks/useAddToCart';
import PublicLayout from '@/Layouts/PublicLayout';
import { cn } from '@/utils/cn';
import { imageUrl } from '@/utils/format';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Search as SearchIcon, SearchX, Store as StoreIcon } from 'lucide-react';

function StoreLogo({ store, className }) {
    return (
        <span className={cn('flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-primary-50', className)}>
            {store.logo ? (
                <img src={imageUrl(store.logo)} alt="" loading="lazy" className="h-full w-full object-cover" />
            ) : (
                <StoreIcon className="h-6 w-6 text-primary-600" aria-hidden="true" />
            )}
        </span>
    );
}

function StoreResult({ store }) {
    const closed = !store.is_open_now;

    return (
        <Link
            href={route('stores.show', store.id)}
            className={cn(
                'flex items-center gap-3 rounded-2xl bg-white p-3 shadow-card ring-1 ring-gray-100 transition hover:shadow-card-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                closed && 'opacity-75',
            )}
        >
            <LazyImage src={imageUrl(store.cover_image)} alt="" className={cn('h-16 w-20 shrink-0 rounded-xl', closed && 'grayscale')} />
            <span className="min-w-0 flex-1">
                <span className="block truncate font-semibold text-secondary-900">{store.name}</span>
                <span className="block truncate text-sm text-gray-500">
                    {[store.category, store.neighborhood].filter(Boolean).join(' · ')}
                </span>
                <OpeningStatusBadge isOpen={store.is_open_now} detail={store.status_detail} className="mt-1 max-w-full" />
            </span>
            <ChevronRight className="h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" />
        </Link>
    );
}

/**
 * Résultats de la recherche globale (/search?q=) : commerces, puis produits regroupés par commerce
 * (ajout au panier possible directement depuis les résultats).
 */
export default function Search({ q, stores, productGroups, minLength }) {
    const cart = useCart();
    const { add, dialog } = useAddToCart();

    const productCount = productGroups.reduce((sum, group) => sum + group.products.length, 0);
    const tooShort = q.length < minLength;
    const nothing = !tooShort && stores.length === 0 && productGroups.length === 0;

    return (
        <PublicLayout searchOnMobile={false}>
            <Head title={q ? `Recherche : ${q}` : 'Recherche'} />

            {/* Sur mobile, la recherche est en haut de la page (pas de doublon dans le header). */}
            <SearchBar key={q} className="mt-5 md:hidden" placeholder="De quoi avez-vous besoin ?" />

            <div className="mt-6">
                {tooShort ? (
                    <h1 className="text-2xl font-bold text-secondary-900">Rechercher sur Gogab</h1>
                ) : (
                    <>
                        <h1 className="text-2xl font-bold text-secondary-900 sm:text-3xl">Résultats pour « {q} »</h1>
                        <p className="mt-1 text-gray-600">
                            {stores.length} commerce{stores.length > 1 ? 's' : ''} · {productCount} produit
                            {productCount > 1 ? 's' : ''}
                        </p>
                    </>
                )}
            </div>

            {tooShort && (
                <EmptyState
                    className="mt-6"
                    icon={SearchIcon}
                    title="Que cherchez-vous ?"
                    description={`Saisissez au moins ${minLength} caractères : nom d’un commerce, d’un plat ou d’un produit.`}
                />
            )}

            {nothing && (
                <EmptyState
                    className="mt-6"
                    icon={SearchX}
                    title="Aucun résultat"
                    description="Essayez un autre mot, ou parcourez les commerces par catégorie."
                    action={
                        <Button href={route('home')} variant="outline">
                            Voir tous les commerces
                        </Button>
                    }
                />
            )}

            {stores.length > 0 && (
                <section aria-labelledby="stores-results" className="mt-8">
                    <h2 id="stores-results" className="mb-3 text-lg font-bold text-secondary-900">
                        Commerces
                    </h2>
                    <div className="grid gap-3 md:grid-cols-2">
                        {stores.map((store) => (
                            <StoreResult key={store.id} store={store} />
                        ))}
                    </div>
                </section>
            )}

            {productGroups.length > 0 && (
                <section aria-labelledby="products-results" className="mt-10">
                    <h2 id="products-results" className="mb-3 text-lg font-bold text-secondary-900">
                        Produits
                    </h2>
                    <div className="space-y-8">
                        {productGroups.map(({ store, products }) => (
                            <section key={store.id} aria-label={`Produits de ${store.name}`}>
                                <Link
                                    href={route('stores.show', store.id)}
                                    className="mb-3 flex min-h-tap items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                >
                                    <StoreLogo store={store} className="h-11 w-11" />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-semibold text-secondary-900">{store.name}</span>
                                        <OpeningStatusBadge isOpen={store.is_open_now} detail={store.status_detail} className="max-w-full" />
                                    </span>
                                    <span className="hidden shrink-0 text-sm font-semibold text-secondary sm:inline">Voir le commerce</span>
                                    <ChevronRight className="h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" />
                                </Link>
                                <div className="grid gap-3 md:grid-cols-2">
                                    {products.map((product) => (
                                        <ProductCard
                                            key={product.id}
                                            product={product}
                                            canOrder={store.is_open_now}
                                            quantity={cart.store?.id === store.id ? cart.quantityOf(product.id) : 0}
                                            onAdd={() => add(product, store)}
                                            onQuantityChange={(quantity) => cart.updateQuantity(product.id, quantity)}
                                            onRemove={() => cart.removeItem(product.id)}
                                        />
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                </section>
            )}

            {dialog}
        </PublicLayout>
    );
}
