import SearchBar from '@/Components/Layout/SearchBar';
import LazyImage from '@/Components/LazyImage';
import OpeningStatusBadge from '@/Components/OpeningStatusBadge';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import { SkeletonCard } from '@/Components/UI/Skeleton';
import { useNeighborhood } from '@/Contexts/NeighborhoodContext';
import PublicLayout from '@/Layouts/PublicLayout';
import { categoryIcon } from '@/utils/categoryIcons';
import { cn } from '@/utils/cn';
import { imageUrl } from '@/utils/format';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Bike, Clock, LayoutGrid, MapPin, SearchX, Store, Tags } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

/**
 * Proximité d'un commerce avec le quartier choisi (pas de GPS) :
 * 0 = même quartier, 1 = même zone, 2 = ailleurs.
 */
function proximity(store, neighborhood) {
    if (!neighborhood) {
        return 2;
    }

    if (store.neighborhood_id === neighborhood.id) {
        return 0;
    }

    return store.zone && store.zone === neighborhood.zone ? 1 : 2;
}

function CategoryPill({ href, active, icon: Icon, label }) {
    return (
        <Link
            href={href}
            preserveState
            preserveScroll
            only={['stores', 'title', 'filters']}
            aria-current={active ? 'true' : undefined}
            className="group flex w-20 shrink-0 flex-col items-center gap-2 rounded-2xl p-1 text-center focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:w-24"
        >
            <span
                className={cn(
                    'flex h-16 w-16 items-center justify-center rounded-full shadow-md transition sm:h-20 sm:w-20',
                    active
                        ? 'bg-accent text-secondary-900 ring-4 ring-white/70'
                        : 'bg-white text-primary-700 group-hover:-translate-y-0.5 group-hover:shadow-lg',
                )}
            >
                <Icon className="h-7 w-7 sm:h-8 sm:w-8" aria-hidden="true" />
            </span>
            <span className={cn('line-clamp-2 text-xs font-semibold leading-tight text-white sm:text-sm', active && 'underline underline-offset-4')}>
                {label}
            </span>
        </Link>
    );
}

function Hero({ categories, filters }) {
    const categoryHref = (slug) => route('home', Object.fromEntries(Object.entries({ category: slug, q: filters.q }).filter(([, v]) => v)));

    return (
        <section className="relative bg-gradient-to-b from-primary-600 to-primary text-white">
            <div className="mx-auto max-w-6xl px-4 pb-16 pt-8 sm:px-6 sm:pb-24 sm:pt-12">
                <h1 className="max-w-2xl text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">
                    Vos commerces de quartier, livrés chez vous
                </h1>
                <p className="mt-2 max-w-xl text-base text-white/90 sm:text-lg">
                    Restaurants, pharmacies, épiceries… partout à Libreville.
                </p>

                <SearchBar size="lg" placeholder="De quoi avez-vous besoin ?" className="mt-6 max-w-2xl" />

                {categories.length > 0 && (
                    <nav aria-label="Catégories" className="-mx-4 mt-8 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        <div className="flex w-max gap-2 sm:w-auto sm:flex-wrap sm:gap-4">
                            <CategoryPill href={categoryHref(null)} active={!filters.category} icon={LayoutGrid} label="Tout" />
                            {categories.map((category) => (
                                <CategoryPill
                                    key={category.slug}
                                    href={categoryHref(category.slug)}
                                    active={filters.category === category.slug}
                                    icon={categoryIcon(category.icon)}
                                    label={category.name}
                                />
                            ))}
                        </div>
                    </nav>
                )}
            </div>

            {/* Base en vague vers le blanc */}
            <svg
                className="absolute inset-x-0 bottom-0 h-10 w-full text-white sm:h-16"
                viewBox="0 0 1440 80"
                preserveAspectRatio="none"
                aria-hidden="true"
            >
                <path
                    fill="currentColor"
                    d="M0,48 C180,80 360,80 540,56 C720,32 900,0 1080,16 C1260,32 1350,56 1440,48 L1440,80 L0,80 Z"
                />
            </svg>
        </section>
    );
}

function StoreCard({ store, nearness }) {
    const closed = !store.is_open_now;

    return (
        <Link
            href={route('stores.show', store.id)}
            className={cn(
                'group flex flex-col overflow-hidden rounded-2xl bg-white shadow-card ring-1 ring-gray-100 transition duration-200 hover:-translate-y-0.5 hover:shadow-card-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                closed && 'opacity-75',
            )}
        >
            <div className="relative">
                <LazyImage
                    src={imageUrl(store.cover_image)}
                    alt=""
                    className={cn('aspect-[16/9] w-full', closed && 'grayscale')}
                />
                {nearness < 2 && (
                    <Badge color="accent" size="sm" icon={MapPin} className="absolute left-3 top-3 shadow-sm">
                        {nearness === 0 ? 'Votre quartier' : 'Votre zone'}
                    </Badge>
                )}
                <span className="absolute -bottom-6 left-4 flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-md ring-4 ring-white">
                    {store.logo ? (
                        <img src={imageUrl(store.logo)} alt="" loading="lazy" className={cn('h-full w-full object-cover', closed && 'grayscale')} />
                    ) : (
                        <Store className="h-6 w-6 text-primary-600" aria-hidden="true" />
                    )}
                </span>
            </div>
            <div className="flex flex-1 flex-col px-4 pb-4 pt-8">
                <h3 className="truncate text-base font-semibold text-secondary-900">{store.name}</h3>
                <p className="mt-0.5 truncate text-sm text-gray-500">
                    {[store.category, store.neighborhood].filter(Boolean).join(' · ')}
                </p>
                <OpeningStatusBadge isOpen={store.is_open_now} detail={store.status_detail} className="mt-3 max-w-full" />
                <p className="mt-1.5 flex items-center gap-1.5 text-xs text-gray-500">
                    <Clock className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                    Aujourd’hui : {store.today_hours}
                </p>
            </div>
        </Link>
    );
}

function JoinBand() {
    const cards = [
        {
            href: route('register.delivery'),
            icon: Bike,
            title: 'Devenir livreur',
            text: 'Livrez dans votre zone, à moto, en voiture ou à vélo, aux heures qui vous conviennent.',
            cta: 'Postuler',
        },
        {
            href: route('register.business'),
            icon: Store,
            title: 'Enregistrer votre commerce',
            text: 'Restaurant, pharmacie, épicerie : recevez des commandes de tout Libreville.',
            cta: 'Inscrire mon commerce',
        },
    ];

    return (
        <section aria-labelledby="join-title" className="mt-16">
            <h2 id="join-title" className="text-2xl font-bold text-secondary-900">
                Rejoignez Gogab
            </h2>
            <div className="mt-4 grid gap-4 md:grid-cols-2">
                {cards.map(({ href, icon: Icon, title, text, cta }) => (
                    <Link
                        key={href}
                        href={href}
                        className="group flex items-start gap-4 rounded-3xl bg-secondary-50 p-5 ring-1 ring-secondary-100 transition hover:bg-secondary-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary sm:p-6"
                    >
                        <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-secondary text-white">
                            <Icon className="h-7 w-7" aria-hidden="true" />
                        </span>
                        <span className="min-w-0">
                            <span className="block text-lg font-bold text-secondary-900">{title}</span>
                            <span className="mt-1 block text-sm text-gray-600">{text}</span>
                            <span className="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-secondary group-hover:gap-2">
                                {cta} <ArrowRight className="h-4 w-4 transition-all" aria-hidden="true" />
                            </span>
                        </span>
                    </Link>
                ))}
            </div>
        </section>
    );
}

/**
 * Accueil marketplace : bandeau vert (recherche + catégories), commerces visibles (ceux du
 * quartier choisi d'abord, les fermés grisés), bandeau « Rejoignez Gogab ».
 */
export default function Home({ stores, categories, title, filters }) {
    const { auth, neighborhoods = [] } = usePage().props;
    const { neighborhoodId } = useNeighborhood();
    const [loading, setLoading] = useState(false);

    // Squelettes pendant les rechargements de la liste (catégorie, recherche).
    useEffect(() => {
        const offStart = router.on('start', (event) => {
            if (new URL(event.detail.visit.url).pathname === '/') {
                setLoading(true);
            }
        });
        const offFinish = router.on('finish', () => setLoading(false));

        return () => {
            offStart();
            offFinish();
        };
    }, []);

    const neighborhood = neighborhoods.find((item) => item.id === neighborhoodId) ?? null;

    // Quartier choisi d'abord, puis sa zone ; à proximité égale, l'ordre du serveur (ouverts d'abord).
    const sorted = useMemo(
        () =>
            stores
                .map((store, index) => ({ store, index, nearness: proximity(store, neighborhood) }))
                .sort((a, b) => a.nearness - b.nearness || a.index - b.index),
        [stores, neighborhood],
    );

    const nearbyCount = sorted.filter((item) => item.nearness === 0).length;
    const hasNearby = sorted.some((item) => item.nearness < 2);
    const showJoin = !auth.user || auth.user.role === 'client';

    return (
        <PublicLayout tone="white" searchOnMobile={false} hero={<Hero categories={categories} filters={filters} />}>
            <Head title={filters.category || filters.q ? title : 'Accueil'} />

            <section aria-labelledby="stores-title" aria-busy={loading || undefined} className="pt-4">
                <div className="flex flex-wrap items-end justify-between gap-x-4 gap-y-1">
                    <h2 id="stores-title" className="text-2xl font-bold text-secondary-900">
                        {title}
                    </h2>
                    {!loading && stores.length > 0 && (
                        <p className="text-sm text-gray-500">
                            {stores.length} commerce{stores.length > 1 ? 's' : ''}
                            {neighborhood && nearbyCount > 0 && ` · ${nearbyCount} à ${neighborhood.name}`}
                        </p>
                    )}
                </div>
                {hasNearby && !loading && (
                    <p className="mt-1 text-sm text-gray-600">
                        Quartier {neighborhood.name} : ses commerces et ceux de la zone {neighborhood.zone} apparaissent en premier.
                    </p>
                )}

                {loading ? (
                    <div className="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {Array.from({ length: 6 }, (_, index) => (
                            <SkeletonCard key={index} />
                        ))}
                    </div>
                ) : stores.length === 0 ? (
                    <EmptyState
                        className="mt-5"
                        icon={filters.q ? SearchX : filters.category ? Tags : Store}
                        title={
                            filters.q
                                ? 'Aucun résultat'
                                : filters.category
                                  ? 'Aucun commerce dans cette catégorie pour le moment'
                                  : 'Aucun commerce pour le moment'
                        }
                        description={
                            filters.q
                                ? 'Essayez un autre mot : nom du commerce, plat ou produit.'
                                : 'De nouveaux commerces de Libreville rejoignent Gogab chaque semaine.'
                        }
                        action={
                            (filters.q || filters.category) && (
                                <Button href={route('home')} variant="outline">
                                    Voir tous les commerces
                                </Button>
                            )
                        }
                    />
                ) : (
                    <div className="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {sorted.map(({ store, nearness }) => (
                            <StoreCard key={store.id} store={store} nearness={nearness} />
                        ))}
                    </div>
                )}
            </section>

            {showJoin && <JoinBand />}
        </PublicLayout>
    );
}
