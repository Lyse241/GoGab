import ReportProblemButton from '@/Components/Reports/ReportProblemButton';
import { SkeletonList } from '@/Components/UI/Skeleton';
import useListLoading from '@/Hooks/useListLoading';
import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/UI/Pagination';
import DeliveryLayout from '@/Layouts/DeliveryLayout';
import { formatFCFA } from '@/utils/format';
import { Head } from '@inertiajs/react';
import { History as HistoryIcon, MapPin, Megaphone, Store } from 'lucide-react';

function Total({ label, value, highlight }) {
    return (
        <Card padding="sm" className={highlight ? 'bg-primary-600 px-3 text-white ring-primary-600' : 'px-3'}>
            <p className={highlight ? 'text-xs font-medium text-primary-50' : 'text-xs font-medium text-gray-500'}>{label}</p>
            <p className="truncate text-lg font-bold">{formatFCFA(value)}</p>
        </Card>
    );
}

/**
 * Historique du livreur : courses livrées (date, commerce, gain) et gains du jour, de la
 * semaine et du mois (heure de Libreville).
 */
export default function History({ deliveries, earnings }) {
    const listLoading = useListLoading();
    return (
        <DeliveryLayout title="Historique" subtitle="Vos courses livrées et vos gains" availability={false}>
            <Head title="Historique" />

            <section aria-label="Gains" className="grid grid-cols-3 gap-2">
                <Total label="Aujourd’hui" value={earnings.today} highlight />
                <Total label="Cette semaine" value={earnings.week} />
                <Total label="Ce mois" value={earnings.month} />
            </section>

            {listLoading ? (
                    <SkeletonList />
                ) : deliveries.data.length === 0 ? (
                <EmptyState
                    icon={HistoryIcon}
                    title="Aucune course livrée"
                    description="Vos courses terminées apparaîtront ici avec vos gains."
                    action={
                        <Button href={route('delivery.offers')} icon={Megaphone}>
                            Voir les offres
                        </Button>
                    }
                />
            ) : (
                <Card padding="none">
                    <ul className="divide-y divide-gray-100">
                        {deliveries.data.map((delivery) => (
                            <li key={delivery.id} className="flex items-center gap-3 px-4 py-3">
                                <div className="w-14 shrink-0 text-center">
                                    <p className="text-sm font-bold text-secondary-900">{delivery.date.slice(0, 5)}</p>
                                    <p className="text-xs text-gray-500">{delivery.time}</p>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="flex items-center gap-1 truncate font-semibold text-gray-900">
                                        <Store className="h-4 w-4 shrink-0 text-primary-600" aria-hidden="true" />
                                        <span className="truncate">{delivery.store}</span>
                                    </p>
                                    <p className="flex items-center gap-1 truncate text-sm text-gray-500">
                                        <MapPin className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                        <span className="truncate">
                                            {delivery.neighborhood} · {delivery.number}
                                        </span>
                                    </p>
                                </div>
                                <div className="flex shrink-0 flex-col items-end">
                                    <p className="font-bold text-primary-700">+{formatFCFA(delivery.earning)}</p>
                                    <ReportProblemButton reporting={delivery.reporting} size="sm" label="Signaler" className="-mr-2 text-gray-500" />
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            <Pagination paginator={deliveries} />
        </DeliveryLayout>
    );
}
