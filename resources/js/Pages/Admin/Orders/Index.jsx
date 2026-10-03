import Badge from '@/Components/UI/Badge';
import { SkeletonList } from '@/Components/UI/Skeleton';
import useListLoading from '@/Hooks/useListLoading';
import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Input from '@/Components/UI/Input';
import Pagination from '@/Components/UI/Pagination';
import Select from '@/Components/UI/Select';
import StatusBadge from '@/Components/UI/StatusBadge';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { cn } from '@/utils/cn';
import { formatFCFA } from '@/utils/format';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, Download, Hourglass, ReceiptText, Search, X } from 'lucide-react';
import { useState } from 'react';

const clean = (filters) => Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== null && value !== ''));

/**
 * Toutes les commandes : filtres (statut, commerce, période), recherche par référence,
 * commandes bloquées en recherche de livreur mises en évidence, export CSV des résultats.
 */
export default function Index({ orders, filters, statuses, stores, stuckCount, stuckMinutes }) {
    const listLoading = useListLoading();
    const { errors } = usePage().props;
    const [reference, setReference] = useState(filters.q ?? '');

    const apply = (changes) =>
        router.get(route('admin.orders.index'), clean({ ...filters, ...changes }), { preserveState: true, preserveScroll: true, replace: true });

    const search = (event) => {
        event.preventDefault();
        apply({ q: reference.trim() || null });
    };

    const hasFilters = Object.values(filters).some(Boolean);

    return (
        <DashboardLayout
            header={<h1 className="text-xl font-bold text-secondary-900">Commandes</h1>}
            actions={
                // Lien classique (pas Inertia) : le navigateur télécharge le fichier.
                <a
                    href={route('admin.orders.export', clean(filters))}
                    className="tap-area inline-flex h-9 items-center gap-1.5 rounded-full border border-gray-300 bg-white px-3.5 text-sm font-semibold text-gray-800 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                >
                    <Download className="h-4 w-4" aria-hidden="true" />
                    Exporter en CSV
                </a>
            }
        >
            <Head title="Commandes" />

            <div className="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                {stuckCount > 0 && filters.status !== 'stuck' && (
                    <button
                        type="button"
                        onClick={() => apply({ status: 'stuck' })}
                        className="flex w-full items-center gap-3 rounded-2xl bg-warning-50 px-4 py-3 text-left text-sm text-warning-900 ring-1 ring-warning-200 hover:bg-warning-100"
                    >
                        <Hourglass className="h-5 w-5 shrink-0" aria-hidden="true" />
                        <span className="flex-1">
                            <span className="font-semibold">
                                {stuckCount} commande{stuckCount > 1 ? 's' : ''} bloquée{stuckCount > 1 ? 's' : ''}
                            </span>{' '}
                            en recherche de livreur depuis plus de {stuckMinutes} minutes.
                        </span>
                        <span className="font-semibold underline">Voir</span>
                    </button>
                )}

                {/* Filtres : une seule rangée au-dessus de la liste. */}
                <Card padding="sm" className="space-y-3 px-4">
                    <form onSubmit={search} className="flex gap-2">
                        <Input
                            id="q"
                            label="Référence"
                            placeholder="GG-000123 ou 123"
                            value={reference}
                            onChange={(event) => setReference(event.target.value)}
                            wrapperClassName="flex-1"
                            error={errors.q}
                        />
                        <Button type="submit" icon={Search} className="mt-6" aria-label="Rechercher">
                            <span className="hidden sm:inline">Rechercher</span>
                        </Button>
                    </form>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Select id="status" label="Statut" placeholder="Tous les statuts" options={statuses} value={filters.status ?? ''} onChange={(e) => apply({ status: e.target.value || null })} error={errors.status} />
                        <Select id="store" label="Commerce" placeholder="Tous les commerces" options={stores} value={filters.store ?? ''} onChange={(e) => apply({ store: e.target.value || null })} error={errors.store} />
                        <Input id="from" type="date" label="Du" value={filters.from ?? ''} max={filters.to ?? undefined} onChange={(e) => apply({ from: e.target.value || null })} error={errors.from} />
                        <Input id="to" type="date" label="Au" value={filters.to ?? ''} min={filters.from ?? undefined} onChange={(e) => apply({ to: e.target.value || null })} error={errors.to} />
                    </div>
                    {hasFilters && (
                        <div className="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600">
                            <span>
                                {orders.total} commande{orders.total > 1 ? 's' : ''}
                            </span>
                            <Button
                                variant="ghost"
                                size="sm"
                                icon={X}
                                onClick={() => {
                                    setReference('');
                                    router.get(route('admin.orders.index'), {}, { preserveScroll: true, replace: true });
                                }}
                            >
                                Effacer les filtres
                            </Button>
                        </div>
                    )}
                </Card>

                {listLoading ? (
                    <SkeletonList />
                ) : orders.data.length === 0 ? (
                    <EmptyState icon={ReceiptText} title="Aucune commande" description={hasFilters ? 'Aucune commande ne correspond à ces filtres.' : 'Les commandes apparaîtront ici.'} />
                ) : (
                    <Card padding="none" className="overflow-hidden">
                        {/* Tableau (desktop) */}
                        <table className="hidden w-full text-left text-sm md:table">
                            <thead className="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Commande</th>
                                    <th className="px-4 py-3 font-medium">Commerce</th>
                                    <th className="px-4 py-3 font-medium">Client</th>
                                    <th className="px-4 py-3 font-medium">Livreur</th>
                                    <th className="px-4 py-3 text-right font-medium">Total</th>
                                    <th className="px-4 py-3 font-medium">Statut</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {orders.data.map((order) => (
                                    <tr
                                        key={order.id}
                                        onClick={() => router.visit(route('admin.orders.show', order.id))}
                                        className={cn('cursor-pointer hover:bg-gray-50', order.is_stuck && 'bg-warning-50/60')}
                                    >
                                        <td className="px-4 py-3">
                                            <Link href={route('admin.orders.show', order.id)} className="font-semibold text-secondary-900 hover:underline">
                                                {order.number}
                                            </Link>
                                            <p className="text-xs text-gray-500">{order.created_at}</p>
                                        </td>
                                        <td className="px-4 py-3 text-gray-800">{order.store ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-800">{order.client ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-800">{order.courier ?? '—'}</td>
                                        <td className="px-4 py-3 text-right font-medium tabular-nums text-gray-900">{formatFCFA(order.total_price)}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <StatusBadge status={order.status} size="sm" />
                                                {order.is_stuck && (
                                                    <Badge color="warning" size="sm" icon={Hourglass}>
                                                        Bloquée · {order.searching_minutes} min
                                                    </Badge>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {/* Cartes (mobile) */}
                        <ul className="divide-y divide-gray-100 md:hidden">
                            {orders.data.map((order) => (
                                <li key={order.id}>
                                    <Link href={route('admin.orders.show', order.id)} className={cn('flex items-start gap-3 p-4 hover:bg-gray-50', order.is_stuck && 'bg-warning-50/60')}>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <p className="font-semibold text-secondary-900">{order.number}</p>
                                                <p className="font-semibold text-gray-900">{formatFCFA(order.total_price)}</p>
                                            </div>
                                            <p className="truncate text-sm text-gray-700">
                                                {order.store ?? '—'} · {order.client ?? '—'}
                                            </p>
                                            <p className="text-xs text-gray-500">{order.created_at}</p>
                                            <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                                <StatusBadge status={order.status} size="sm" />
                                                {order.is_stuck && (
                                                    <Badge color="warning" size="sm" icon={Hourglass}>
                                                        Bloquée · {order.searching_minutes} min
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                        <ChevronRight className="mt-1 h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}

                <Pagination paginator={orders} />
            </div>
        </DashboardLayout>
    );
}
