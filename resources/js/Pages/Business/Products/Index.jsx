import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import EmptyState from '@/Components/UI/EmptyState';
import Input from '@/Components/UI/Input';
import Switch from '@/Components/UI/Switch';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { cn } from '@/utils/cn';
import { formatFCFA, imageUrl } from '@/utils/format';
import { Head, router } from '@inertiajs/react';
import { ImageOff, Package, Pencil, Plus, Search, SearchX, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const OTHERS = 'Autres produits';

/**
 * Catalogue de l'entreprise : produits groupés par section, recherche, filtre par section,
 * interrupteur de disponibilité (mise à jour immédiate), modification et suppression.
 */
export default function Index({ products, sections, filters, noSection, totals }) {
    const [search, setSearch] = useState(filters.q);
    const [deleting, setDeleting] = useState(null);
    // Le produit reste affiché dans la fenêtre pendant son animation de fermeture.
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [deletingInProgress, setDeletingInProgress] = useState(false);
    // Disponibilité affichée tout de suite, avant la réponse du serveur.
    const [availability, setAvailability] = useState({});
    const [saving, setSaving] = useState({});
    const firstRender = useRef(true);
    const searchTimer = useRef(null);
    // Derniers filtres demandés : une recherche différée ne doit pas annuler un clic sur une section.
    const latest = useRef(filters);

    const applyFilters = (next) => {
        clearTimeout(searchTimer.current);
        latest.current = { ...latest.current, ...next };

        router.get(
            route('business.products.index'),
            Object.fromEntries(Object.entries(latest.current).filter(([, value]) => value)),
            { preserveState: true, preserveScroll: true, replace: true, only: ['products', 'filters'] },
        );
    };

    // Recherche au fil de la saisie (300 ms après la dernière touche).
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return undefined;
        }

        searchTimer.current = setTimeout(() => applyFilters({ q: search.trim() }), 300);

        return () => clearTimeout(searchTimer.current);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    // Les données serveur font foi dès qu'elles arrivent.
    useEffect(() => setAvailability({}), [products]);

    const groups = useMemo(() => {
        const map = new Map();
        products.forEach((product) => {
            const key = product.menu_section ?? OTHERS;
            map.set(key, [...(map.get(key) ?? []), product]);
        });

        return [...map.entries()];
    }, [products]);

    const toggle = (product, value) => {
        setAvailability((current) => ({ ...current, [product.id]: value }));
        setSaving((current) => ({ ...current, [product.id]: true }));

        router.patch(
            route('business.products.availability', product.id),
            { is_available: value },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['products', 'totals', 'flash'],
                onError: () => setAvailability((current) => ({ ...current, [product.id]: !value })),
                onFinish: () => setSaving(({ [product.id]: _, ...rest }) => rest),
            },
        );
    };

    const destroy = () =>
        router.delete(route('business.products.destroy', deleting.id), {
            preserveScroll: true,
            onStart: () => setDeletingInProgress(true),
            onFinish: () => {
                setDeletingInProgress(false);
                setConfirmOpen(false);
            },
        });

    const filtering = filters.q !== '' || filters.section !== '';

    return (
        <DashboardLayout
            header={
                <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-xl font-bold text-secondary-900">Produits</h1>
                        <p className="text-sm text-gray-500">
                            {totals.all} produit{totals.all > 1 ? 's' : ''} · {totals.available} disponible{totals.available > 1 ? 's' : ''}
                        </p>
                    </div>
                    <Button href={route('business.products.create')} size="sm" icon={Plus}>
                        Ajouter
                    </Button>
                </div>
            }
        >
            <Head title="Produits" />

            <div className="mx-auto max-w-4xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                {totals.all === 0 ? (
                    <EmptyState
                        icon={Package}
                        title="Votre catalogue est vide"
                        description="Ajoutez vos produits avec leur prix et une photo : ils apparaîtront sur la page de votre commerce."
                        action={<Button href={route('business.products.create')} icon={Plus}>Ajouter un produit</Button>}
                    />
                ) : (
                    <>
                        <Input
                            id="product-search"
                            type="search"
                            icon={Search}
                            placeholder="Rechercher un produit"
                            aria-label="Rechercher un produit"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />

                        {sections.length > 0 && (
                            <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="Filtrer par section">
                                {[{ value: '', label: 'Toutes' }, ...sections.map((s) => ({ value: s, label: s })), { value: noSection, label: 'Sans section' }].map(({ value, label }) => (
                                    <button
                                        key={value || 'all'}
                                        type="button"
                                        onClick={() => applyFilters({ section: value, q: search.trim() })}
                                        aria-pressed={filters.section === value}
                                        className={cn(
                                            'h-9 shrink-0 rounded-full px-4 text-sm font-semibold ring-1 ring-inset transition',
                                            filters.section === value
                                                ? 'bg-secondary text-white ring-secondary'
                                                : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50',
                                        )}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        )}

                        {products.length === 0 ? (
                            <EmptyState
                                icon={SearchX}
                                title="Aucun produit trouvé"
                                description="Essayez un autre mot ou une autre section."
                                action={
                                    filtering && (
                                        <Button
                                            variant="outline"
                                            onClick={() => {
                                                setSearch('');
                                                applyFilters({ q: '', section: '' });
                                            }}
                                        >
                                            Effacer les filtres
                                        </Button>
                                    )
                                }
                            />
                        ) : (
                            groups.map(([section, items]) => (
                                <section key={section} aria-labelledby={`section-${section}`}>
                                    <h2 id={`section-${section}`} className="mb-2 flex items-baseline gap-2 px-1 text-sm font-bold uppercase tracking-wide text-gray-500">
                                        {section}
                                        <span className="font-medium normal-case tracking-normal text-gray-400">({items.length})</span>
                                    </h2>
                                    <Card padding="none">
                                        <ul className="divide-y divide-gray-100">
                                            {items.map((product) => {
                                                const available = availability[product.id] ?? product.is_available;

                                                return (
                                                    <li key={product.id} className={cn('flex items-center gap-3 p-3 sm:p-4', !available && 'bg-gray-50')}>
                                                        <div className={cn('flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gray-100', !available && 'opacity-50')}>
                                                            {product.image ? (
                                                                <img src={imageUrl(product.image)} alt="" loading="lazy" className="h-full w-full object-cover" />
                                                            ) : (
                                                                <ImageOff className="h-6 w-6 text-gray-300" aria-hidden="true" />
                                                            )}
                                                        </div>
                                                        <div className="min-w-0 flex-1">
                                                            <p className={cn('truncate font-semibold', available ? 'text-gray-900' : 'text-gray-500')}>{product.name}</p>
                                                            <p className="text-sm font-semibold text-primary-700">{formatFCFA(product.price)}</p>
                                                            <Switch
                                                                className="mt-1"
                                                                size="sm"
                                                                checked={available}
                                                                onChange={(value) => toggle(product, value)}
                                                                loading={saving[product.id]}
                                                                label={<span className={cn('text-xs font-medium', available ? 'text-primary-700' : 'text-gray-500')}>{available ? 'Disponible' : 'Indisponible'}</span>}
                                                                reverse
                                                            />
                                                        </div>
                                                        <div className="flex shrink-0 flex-col gap-1 sm:flex-row">
                                                            <Button
                                                                href={route('business.products.edit', product.id)}
                                                                variant="ghost"
                                                                size="icon-sm"
                                                                icon={Pencil}
                                                                aria-label={`Modifier ${product.name}`}
                                                            />
                                                            <Button
                                                                variant="ghost"
                                                                size="icon-sm"
                                                                icon={Trash2}
                                                                onClick={() => {
                                                                    setDeleting(product);
                                                                    setConfirmOpen(true);
                                                                }}
                                                                className="text-danger-600 hover:bg-danger-50"
                                                                aria-label={`Supprimer ${product.name}`}
                                                            />
                                                        </div>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </Card>
                                </section>
                            ))
                        )}
                    </>
                )}
            </div>

            <ConfirmDialog
                open={confirmOpen}
                onClose={() => setConfirmOpen(false)}
                onConfirm={destroy}
                loading={deletingInProgress}
                title={deleting ? `Supprimer « ${deleting.name} » ?` : ''}
                message="Le produit et sa photo seront supprimés définitivement. S’il figure déjà dans des commandes, rendez-le plutôt indisponible."
                confirmLabel="Supprimer"
            />
        </DashboardLayout>
    );
}
