import { Avatar } from '@/Components/Layout/UserMenu';
import { SkeletonList } from '@/Components/UI/Skeleton';
import useListLoading from '@/Hooks/useListLoading';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/UI/Pagination';
import StatusBadge from '@/Components/UI/StatusBadge';
import Tabs from '@/Components/UI/Tabs';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { cn } from '@/utils/cn';
import { Head, Link, router } from '@inertiajs/react';
import { Bike, CalendarDays, FileText, Flag, Mail, Phone, Search, ShoppingBag, Store, UserCheck, Users, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export const TYPE_BADGES = {
    client: { color: 'primary', icon: ShoppingBag },
    delivery: { color: 'secondary', icon: Bike },
    business: { color: 'accent', icon: Store },
};

const STATUS_TABS = {
    validation: [
        { value: 'pending', label: 'En attente' },
        { value: 'approved', label: 'Approuvés' },
        { value: 'rejected', label: 'Rejetés' },
        { value: 'suspended', label: 'Suspendus' },
    ],
    directory: [
        { value: 'all', label: 'Tous' },
        { value: 'approved', label: 'Approuvés' },
        { value: 'pending', label: 'En attente' },
        { value: 'rejected', label: 'Rejetés' },
        { value: 'suspended', label: 'Suspendus' },
    ],
};

const EMPTY = {
    all: { title: 'Aucun compte', description: 'Les comptes inscrits apparaîtront ici.' },
    pending: { title: 'Aucun compte en attente', description: 'Tous les dossiers ont été traités. Bravo !' },
    approved: { title: 'Aucun compte approuvé', description: 'Les comptes validés apparaîtront ici.' },
    rejected: { title: 'Aucun compte rejeté', description: 'Les inscriptions refusées apparaîtront ici.' },
    suspended: { title: 'Aucun compte suspendu', description: 'Les comptes désactivés apparaîtront ici.' },
};

/**
 * Drapeau d'un compte signalé en interne par un administrateur.
 */
function FlagIcon({ account }) {
    if (!account.flagged) {
        return null;
    }

    return (
        <span className="inline-flex shrink-0 text-purple-600" title="Compte signalé">
            <Flag className="h-4 w-4 fill-current" aria-hidden="true" />
            <span className="sr-only">(signalé)</span>
        </span>
    );
}

function TypeBadge({ account }) {
    const type = TYPE_BADGES[account.role] ?? TYPE_BADGES.client;

    return (
        <Badge color={type.color} icon={type.icon} size="sm">
            {account.role_label}
        </Badge>
    );
}

export default function Index({ page, accounts, filters, counts, flaggedCount, types }) {
    const listLoading = useListLoading();
    const directory = page.mode === 'directory';
    const [query, setQuery] = useState(filters.q ?? '');
    const firstRender = useRef(true);

    const visit = (changes) =>
        router.get(
            route(page.route),
            // Valeurs par défaut omises de l'URL (onglet par défaut, tous les types, non signalés).
            Object.fromEntries(
                Object.entries({ ...filters, ...changes })
                    .filter(([key, value]) => value && !(key === 'status' && value === page.default_status))
                    .filter(([key]) => !(key === 'type' && page.locked_type))
                    .map(([key, value]) => [key, value === true ? 1 : value]),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    // Recherche au fil de la frappe (avec un léger délai).
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        const timer = setTimeout(() => visit({ q: query.trim() }), 350);

        return () => clearTimeout(timer);
    }, [query]);

    const empty = EMPTY[filters.status] ?? EMPTY.pending;
    const filtered = Boolean(filters.q || filters.flagged || (filters.type && !page.locked_type));

    return (
        <DashboardLayout
            header={
                <div>
                    <h1 className="text-xl font-bold text-secondary-900">{page.title}</h1>
                    <p className="mt-0.5 text-sm text-gray-500">
                        {directory
                            ? `${counts.all} compte${counts.all > 1 ? 's' : ''}`
                            : counts.pending > 0
                              ? `${counts.pending} compte${counts.pending > 1 ? 's' : ''} en attente de validation`
                              : 'Aucun compte en attente'}
                    </p>
                </div>
            }
        >
            <Head title={page.title} />

            <div className="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                <Tabs
                    label="Statut des comptes"
                    value={filters.status}
                    onChange={(status) => visit({ status, page: null })}
                    items={STATUS_TABS[page.mode].map((tab) => ({ ...tab, count: counts[tab.value] ?? 0 }))}
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <form role="search" onSubmit={(event) => event.preventDefault()} className="relative flex-1">
                        <label htmlFor="accounts-search" className="sr-only">
                            Rechercher par nom, téléphone ou e-mail
                        </label>
                        <Search className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" aria-hidden="true" />
                        <input
                            id="accounts-search"
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Nom, téléphone ou e-mail"
                            className="h-11 w-full rounded-xl border-gray-300 bg-white pl-11 pr-10 text-base shadow-sm focus:border-primary focus:ring-2 focus:ring-primary-100 sm:text-sm [&::-webkit-search-cancel-button]:hidden"
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
                    </form>

                    <div role="group" aria-label="Filtres" className="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                        <button
                            type="button"
                            aria-pressed={filters.flagged}
                            onClick={() => visit({ flagged: !filters.flagged, page: null })}
                            className={cn(
                                'inline-flex min-h-tap shrink-0 items-center gap-1.5 rounded-full px-4 text-sm font-semibold ring-1 transition',
                                filters.flagged ? 'bg-purple-600 text-white ring-purple-600' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50',
                            )}
                        >
                            <Flag className="h-4 w-4" aria-hidden="true" />
                            Comptes signalés
                            <span className={cn('rounded-full px-1.5 text-xs', filters.flagged ? 'bg-white/20' : 'bg-gray-100 text-gray-600')}>{flaggedCount}</span>
                        </button>
                        {!page.locked_type && [{ value: null, label: 'Tous' }, ...types].map((type) => {
                            const active = (filters.type ?? null) === type.value;

                            return (
                                <button
                                    key={type.value ?? 'all'}
                                    type="button"
                                    aria-pressed={active}
                                    onClick={() => visit({ type: type.value, page: null })}
                                    className={cn(
                                        'inline-flex min-h-tap shrink-0 items-center rounded-full px-4 text-sm font-semibold ring-1 transition',
                                        active ? 'bg-secondary text-white ring-secondary' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50',
                                    )}
                                >
                                    {type.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {listLoading ? (
                    <SkeletonList />
                ) : accounts.data.length === 0 ? (
                    <EmptyState
                        icon={filtered ? Search : directory ? Users : UserCheck}
                        title={filtered ? 'Aucun résultat' : empty.title}
                        description={filtered ? 'Aucun compte ne correspond à ces critères.' : empty.description}
                        action={
                            filtered ? (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setQuery('');
                                        visit({ q: null, type: null, flagged: null });
                                    }}
                                >
                                    Effacer les filtres
                                </Button>
                            ) : null
                        }
                    />
                ) : (
                    <>
                        {/* Desktop : tableau */}
                        <Card padding="none" className="hidden overflow-hidden md:block">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th scope="col" className="px-4 py-3">Nom</th>
                                        <th scope="col" className="px-4 py-3">Type</th>
                                        <th scope="col" className="px-4 py-3">Téléphone</th>
                                        <th scope="col" className="px-4 py-3">Inscription</th>
                                        <th scope="col" className="px-4 py-3 text-center">Documents</th>
                                        <th scope="col" className="px-4 py-3">Statut</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {accounts.data.map((account) => (
                                        <tr key={account.id} className="relative cursor-pointer hover:bg-gray-50/60">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <Avatar name={account.name} />
                                                    <div className="min-w-0">
                                                        <div className="flex items-center gap-1.5">
                                                            <Link
                                                                href={route('admin.accounts.show', account.id)}
                                                                className="truncate font-semibold text-gray-900 after:absolute after:inset-0 hover:text-primary-700 focus:outline-none focus-visible:underline"
                                                            >
                                                                {account.name}
                                                            </Link>
                                                            <FlagIcon account={account} />
                                                        </div>
                                                        <p className="truncate text-xs text-gray-500">{account.email}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3"><TypeBadge account={account} /></td>
                                            <td className="whitespace-nowrap px-4 py-3 text-gray-700">{account.phone}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-gray-700">
                                                <time dateTime={account.registered_at_iso}>{account.registered_at}</time>
                                            </td>
                                            <td className="px-4 py-3 text-center text-gray-700">{account.documents_count}</td>
                                            <td className="px-4 py-3"><StatusBadge type="account" status={account.account_status} size="sm" /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Card>

                        {/* Mobile : cartes */}
                        <ul className="space-y-3 md:hidden">
                            {accounts.data.map((account) => (
                                <li key={account.id}>
                                    <Card href={route('admin.accounts.show', account.id)}>
                                        <div className="flex items-start gap-3">
                                            <Avatar name={account.name} />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-start justify-between gap-2">
                                                    <p className="flex items-center gap-1.5 font-semibold text-gray-900">
                                                        {account.name}
                                                        <FlagIcon account={account} />
                                                    </p>
                                                    <StatusBadge type="account" status={account.account_status} size="sm" />
                                                </div>
                                                <TypeBadge account={account} />
                                            </div>
                                        </div>
                                        <dl className="mt-3 grid grid-cols-2 gap-2 text-sm text-gray-700">
                                            <div className="col-span-2 flex items-center gap-2 truncate">
                                                <dt><Mail className="h-4 w-4 text-gray-400" aria-label="E-mail" /></dt>
                                                <dd className="truncate">{account.email}</dd>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <dt><Phone className="h-4 w-4 text-gray-400" aria-label="Téléphone" /></dt>
                                                <dd>{account.phone}</dd>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <dt><CalendarDays className="h-4 w-4 text-gray-400" aria-label="Inscription" /></dt>
                                                <dd>{account.registered_at}</dd>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <dt><FileText className="h-4 w-4 text-gray-400" aria-label="Documents" /></dt>
                                                <dd>
                                                    {account.documents_count} document{account.documents_count > 1 ? 's' : ''}
                                                </dd>
                                            </div>
                                        </dl>
                                    </Card>
                                </li>
                            ))}
                        </ul>

                        <Pagination paginator={accounts} />
                    </>
                )}
            </div>
        </DashboardLayout>
    );
}
