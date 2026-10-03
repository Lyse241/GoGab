import { StoreThumb } from '@/Components/Cart/CartList';
import { SkeletonList } from '@/Components/UI/Skeleton';
import useListLoading from '@/Hooks/useListLoading';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/UI/Pagination';
import StatusBadge from '@/Components/UI/StatusBadge';
import Tabs from '@/Components/UI/Tabs';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatFCFA } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight, ReceiptText } from 'lucide-react';

/**
 * « Mes commandes » : onglets En cours / Terminées, une carte par commande (commerce, date,
 * total, statut), pagination.
 */
export default function Index({ orders, tab, counts }) {
    const listLoading = useListLoading();
    const changeTab = (value) =>
        router.get(route('orders.index'), value === 'finished' ? { tab: value } : {}, { preserveScroll: true, only: ['orders', 'tab', 'counts'] });

    return (
        <PublicLayout>
            <Head title="Mes commandes" />

            <div className="mx-auto max-w-2xl pt-6">
                <h1 className="text-2xl font-bold text-secondary-900">Mes commandes</h1>

                <Tabs
                    className="mt-4"
                    label="Filtrer mes commandes"
                    value={tab}
                    onChange={changeTab}
                    items={[
                        { value: 'ongoing', label: 'En cours', count: counts.ongoing },
                        { value: 'finished', label: 'Terminées', count: counts.finished },
                    ]}
                />

                {listLoading ? (
                    <SkeletonList />
                ) : orders.data.length === 0 ? (
                    <EmptyState
                        className="mt-5"
                        icon={ReceiptText}
                        title={tab === 'finished' ? 'Aucune commande terminée' : 'Aucune commande en cours'}
                        description={
                            tab === 'finished'
                                ? 'Vos commandes livrées, refusées ou annulées apparaîtront ici.'
                                : 'Vos commandes en cours de préparation ou de livraison apparaîtront ici.'
                        }
                        action={<Button href={route('home')}>Voir les commerces</Button>}
                    />
                ) : (
                    <ul className="mt-5 space-y-3">
                        {orders.data.map((order) => (
                            <li key={order.id}>
                                <Link
                                    href={route('orders.show', order.id)}
                                    className="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-card ring-1 ring-gray-100 transition hover:shadow-card-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                >
                                    {order.store && <StoreThumb store={order.store} />}
                                    <span className="min-w-0 flex-1">
                                        <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span className="truncate font-semibold text-secondary-900">{order.store?.name ?? 'Commerce supprimé'}</span>
                                            <StatusBadge status={order.status} size="sm" />
                                        </span>
                                        <span className="mt-1 block text-sm text-gray-500">
                                            {order.number} · {order.created_at}
                                        </span>
                                        <span className="mt-0.5 block text-sm text-gray-700">
                                            {order.items_count} article{order.items_count > 1 ? 's' : ''} ·{' '}
                                            <span className="font-semibold">{formatFCFA(order.total_price)}</span>
                                        </span>
                                    </span>
                                    <ChevronRight className="h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination paginator={orders} className="mt-5" />
            </div>
        </PublicLayout>
    );
}
