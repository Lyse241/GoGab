import { DAYS } from '@/Components/OpeningHoursEditor';
import OpeningStatusBadge from '@/Components/OpeningStatusBadge';
import ProductCard from '@/Components/ProductCard';
import Badge from '@/Components/UI/Badge';
import EmptyState from '@/Components/UI/EmptyState';
import { useCart } from '@/Contexts/CartContext';
import useAddToCart from '@/Hooks/useAddToCart';
import PublicLayout from '@/Layouts/PublicLayout';
import { cartTotals } from '@/utils/cartTotals';
import { cn } from '@/utils/cn';
import { formatFCFA, imageUrl } from '@/utils/format';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock, MapPin, Package, Search, SearchX, Store as StoreIcon, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const OTHERS = 'Autres produits';

function hoursLabel(day) {
    if (day.is_closed || !day.opens_at) {
        return 'Fermé';
    }

    return day.opens_at === day.closes_at
        ? '24 h/24'
        : `${day.opens_at.replace(':', 'h')} – ${day.closes_at.replace(':', 'h')}`;
}

/**
 * Sections du menu, dans l'ordre reçu du serveur (alphabétique, sans section en dernier).
 */
function groupBySection(products) {
    const groups = new Map();
    products.forEach((product) => {
        const key = product.menu_section ?? OTHERS;
        groups.set(key, [...(groups.get(key) ?? []), product]);
    });

    return [...groups.entries()].map(([name, items], index) => ({ id: `section-${index}`, name, items }));
}

function StoreHeader({ store }) {
    return (
        <section className="mt-4">
            <div className="relative">
                <div className="h-40 overflow-hidden rounded-3xl bg-gradient-to-br from-primary-600 to-secondary sm:h-64">
                    {store.cover_image && (
                        <img src={imageUrl(store.cover_image)} alt="" className="h-full w-full object-cover" />
                    )}
                </div>
                <span className="absolute -bottom-8 left-4 flex h-20 w-20 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-md ring-4 ring-white sm:left-6 sm:h-24 sm:w-24">
                    {store.logo ? (
                        <img src={imageUrl(store.logo)} alt={`Logo de ${store.name}`} className="h-full w-full object-cover" />
                    ) : (
                        <StoreIcon className="h-9 w-9 text-primary-600" aria-hidden="true" />
                    )}
                </span>
            </div>

            <div className="mt-10 grid gap-4 lg:grid-cols-[1fr_20rem]">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge color="accent" size="sm">
                            {store.category}
                        </Badge>
                        {store.neighborhood && (
                            <span className="inline-flex items-center gap-1 text-sm text-gray-600">
                                <MapPin className="h-4 w-4 text-primary-600" aria-hidden="true" />
                                {store.neighborhood}
                                {store.zone && <span className="text-gray-400">· zone {store.zone}</span>}
                            </span>
                        )}
                    </div>
                    <h1 className="mt-2 text-2xl font-bold text-secondary-900 sm:text-3xl">{store.name}</h1>
                    <OpeningStatusBadge isOpen={store.is_open_now} detail={store.status_detail} className="mt-2 max-w-full" />
                    {store.description && <p className="mt-3 max-w-2xl text-gray-700">{store.description}</p>}
                    {store.address_landmarks && (
                        <p className="mt-2 max-w-2xl text-sm text-gray-500">{store.address_landmarks}</p>
                    )}
                </div>

                <details className="group self-start rounded-2xl bg-white p-4 ring-1 ring-gray-100 shadow-card">
                    <summary className="flex min-h-tap cursor-pointer list-none items-center justify-between gap-2 font-semibold text-secondary-900">
                        <span className="inline-flex items-center gap-2">
                            <Clock className="h-5 w-5 text-primary-600" aria-hidden="true" />
                            Horaires de la semaine
                        </span>
                        <span className="text-sm font-medium text-secondary group-open:hidden">Afficher</span>
                        <span className="hidden text-sm font-medium text-secondary group-open:inline">Masquer</span>
                    </summary>
                    <ul className="mt-2 space-y-1 text-sm">
                        {store.opening_hours.map((day, index) => (
                            <li
                                key={day.day_of_week}
                                className={cn(
                                    'flex justify-between rounded-lg px-2 py-1.5',
                                    day.day_of_week === store.today ? 'bg-primary-50 font-semibold text-gray-900' : 'text-gray-700',
                                )}
                            >
                                <span>
                                    {DAYS[index]}
                                    {day.day_of_week === store.today && <span className="sr-only"> (aujourd’hui)</span>}
                                </span>
                                <span>{hoursLabel(day)}</span>
                            </li>
                        ))}
                    </ul>
                </details>
            </div>
        </section>
    );
}

/**
 * Page d'un commerce : en-tête, horaires, barre de sections collante (ancres), recherche dans
 * le menu (côté client), produits avec ajout au panier. Commerce fermé : bandeau (message du
 * serveur) et ajout désactivé ; produits indisponibles grisés.
 */
export default function Store({ store, products }) {
    const cart = useCart();
    const { add, dialog } = useAddToCart();
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(null);

    const canOrder = store.is_open_now;
    const term = query.trim().toLocaleLowerCase('fr');

    const sections = useMemo(
        () => groupBySection(term ? products.filter((product) => product.name.toLocaleLowerCase('fr').includes(term)) : products),
        [products, term],
    );
    const hasSections = products.some((product) => product.menu_section);

    // Section visible surlignée dans la barre pendant le défilement.
    useEffect(() => {
        const elements = sections.map((section) => document.getElementById(section.id)).filter(Boolean);
        if (elements.length === 0 || typeof IntersectionObserver === 'undefined') {
            return undefined;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
                if (visible[0]) {
                    setActive(visible[0].target.id);
                }
            },
            { rootMargin: '-45% 0px -50% 0px' },
        );
        elements.forEach((element) => observer.observe(element));

        return () => observer.disconnect();
    }, [sections]);

    // Chip active toujours visible dans la barre : on ne fait défiler que la barre (horizontalement),
    // jamais la page, pour ne pas interrompre le défilement vers la section.
    useEffect(() => {
        const chip = document.getElementById(`chip-${active}`);
        const bar = chip?.closest('nav');
        if (!chip || !bar) {
            return;
        }

        const chipBox = chip.getBoundingClientRect();
        const left = bar.scrollLeft + chipBox.left - bar.getBoundingClientRect().left - (bar.clientWidth - chipBox.width) / 2;
        bar.scrollTo({ left: Math.max(0, left), behavior: 'smooth' });
    }, [active]);

    const goTo = (event, id) => {
        event.preventDefault();
        setActive(id);
        document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        window.history.replaceState(null, '', `#${id}`);
    };

    const cartIsHere = cart.store?.id === store.id && cart.itemCount > 0;
    const { total, count } = cartTotals(
        cart.items,
        new Set(products.filter((product) => !product.is_available).map((product) => product.id)),
    );

    return (
        <PublicLayout>
            <Head title={store.name} />

            <Link
                href={route('home')}
                className="mt-4 inline-flex min-h-tap items-center gap-1.5 text-sm font-medium text-secondary hover:underline"
            >
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Tous les commerces
            </Link>

            <StoreHeader store={store} />

            {!canOrder && (
                <div role="status" className="mt-5 flex items-start gap-3 rounded-2xl border border-accent-300 bg-accent-50 p-4">
                    <Clock className="mt-0.5 h-5 w-5 shrink-0 text-accent-700" aria-hidden="true" />
                    <div>
                        <p className="font-semibold text-secondary-900">{store.status_label}</p>
                        <p className="mt-0.5 text-sm text-gray-700">
                            Vous pouvez consulter le menu, mais les commandes ne sont pas possibles pour le moment.
                        </p>
                    </div>
                </div>
            )}

            {products.length === 0 ? (
                <EmptyState className="mt-6" icon={Package} title="Menu à venir" description="Ce commerce n’a pas encore ajouté de produits." />
            ) : (
                <>
                    {/* Barre collante : recherche dans le menu + sections */}
                    <div className="sticky top-[var(--header-h,4rem)] z-20 -mx-4 mt-6 border-b border-gray-100 bg-gray-50/95 px-4 pb-2 pt-3 backdrop-blur sm:-mx-6 sm:px-6">
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" aria-hidden="true" />
                            <label htmlFor="menu-search" className="sr-only">
                                Rechercher dans le menu
                            </label>
                            <input
                                id="menu-search"
                                type="search"
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder={`Rechercher chez ${store.name}`}
                                className="h-11 w-full rounded-full border-0 bg-white pl-11 pr-11 text-base shadow-sm ring-1 ring-gray-200 placeholder:text-gray-500 focus:ring-2 focus:ring-primary sm:text-sm [&::-webkit-search-cancel-button]:hidden"
                            />
                            {query && (
                                <button
                                    type="button"
                                    onClick={() => setQuery('')}
                                    className="absolute right-1.5 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100"
                                    aria-label="Effacer la recherche"
                                >
                                    <X className="h-4 w-4" aria-hidden="true" />
                                </button>
                            )}
                        </div>

                        {hasSections && sections.length > 0 && (
                            <nav aria-label="Sections du menu" className="-mx-4 mt-2 overflow-x-auto px-4 sm:-mx-6 sm:px-6 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                <ul className="flex w-max gap-2 py-1">
                                    {sections.map((section) => (
                                        <li key={section.id}>
                                            <a
                                                id={`chip-${section.id}`}
                                                href={`#${section.id}`}
                                                onClick={(event) => goTo(event, section.id)}
                                                aria-current={active === section.id ? 'true' : undefined}
                                                className={cn(
                                                    'inline-flex h-10 items-center whitespace-nowrap rounded-full px-4 text-sm font-semibold ring-1 ring-inset transition',
                                                    active === section.id
                                                        ? 'bg-secondary text-white ring-secondary'
                                                        : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50',
                                                )}
                                            >
                                                {section.name}
                                                <span className={cn('ml-1.5 text-xs', active === section.id ? 'text-white/70' : 'text-gray-400')}>
                                                    {section.items.length}
                                                </span>
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </nav>
                        )}
                    </div>

                    {sections.length === 0 ? (
                        <EmptyState
                            className="mt-6"
                            icon={SearchX}
                            title="Aucun produit trouvé"
                            description={`Rien ne correspond à « ${query.trim()} » chez ${store.name}.`}
                        />
                    ) : (
                        <div className="mt-4 space-y-8">
                            {sections.map((section) => (
                                <section
                                    key={section.id}
                                    id={section.id}
                                    aria-labelledby={hasSections ? `${section.id}-title` : undefined}
                                    className="scroll-mt-[calc(var(--header-h,4rem)+8.5rem)]"
                                >
                                    {hasSections && (
                                        <h2 id={`${section.id}-title`} className="mb-3 text-lg font-bold text-secondary-900">
                                            {section.name}
                                        </h2>
                                    )}
                                    <div className="grid gap-3 md:grid-cols-2">
                                        {section.items.map((product) => (
                                            <ProductCard
                                                key={product.id}
                                                product={product}
                                                canOrder={canOrder}
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
                    )}
                </>
            )}

            {/* Récapitulatif fixé en bas de l'écran (à portée de pouce sur mobile) */}
            {cartIsHere && count > 0 && (
                <>
                    <div className="h-20" aria-hidden="true" />
                    <div className="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-4px_12px_rgba(0,0,0,0.06)]">
                        <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-1 sm:px-6">
                            <div>
                                <p className="text-sm text-gray-600">
                                    Panier · {count} article{count > 1 ? 's' : ''}
                                </p>
                                <p className="text-lg font-bold text-gray-900">{formatFCFA(total)}</p>
                            </div>
                            <Link
                                href={route('cart')}
                                className="inline-flex min-h-tap items-center rounded-full bg-primary-600 px-6 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                            >
                                Voir le panier
                            </Link>
                        </div>
                    </div>
                </>
            )}

            {dialog}
        </PublicLayout>
    );
}
