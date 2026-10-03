import Badge from '@/Components/UI/Badge';
import { SkeletonList } from '@/Components/UI/Skeleton';
import useListLoading from '@/Hooks/useListLoading';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/UI/Pagination';
import Select from '@/Components/UI/Select';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

/**
 * Journal des dernières actions de modération, filtrable par type, motif et administrateur.
 */
export default function Index({ actions, filters, types, reasons, admins }) {
    const listLoading = useListLoading();
    const filter = (key, value) =>
        router.get(
            route('admin.moderation.index'),
            Object.fromEntries(Object.entries({ ...filters, [key]: value || null }).filter(([, v]) => v)),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <DashboardLayout header={<h1 className="text-xl font-bold text-secondary-900">Modération</h1>}>
            <Head title="Modération" />

            <div className="mx-auto max-w-5xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                <div className="grid gap-3 sm:grid-cols-3">
                    <Select id="filter-type" label="Type" placeholder="Tous les types" options={types} value={filters.type ?? ''} onChange={(e) => filter('type', e.target.value)} />
                    <Select id="filter-reason" label="Motif" placeholder="Tous les motifs" options={reasons} value={filters.reason ?? ''} onChange={(e) => filter('reason', e.target.value)} />
                    <Select id="filter-admin" label="Administrateur" placeholder="Tous" options={admins} value={filters.admin ?? ''} onChange={(e) => filter('admin', e.target.value)} />
                </div>

                {listLoading ? (
                    <SkeletonList />
                ) : actions.data.length === 0 ? (
                    <EmptyState icon={ShieldCheck} title="Aucune action de modération" description="Rien ne correspond à ces filtres." />
                ) : (
                    <Card padding="none">
                        <ul className="divide-y divide-gray-100">
                            {actions.data.map((action) => (
                                <li key={action.id} className="p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge color={action.type_color} size="sm">{action.type_label}</Badge>
                                            {action.reason_label && <span className="text-sm font-medium text-gray-800">{action.reason_label}</span>}
                                        </div>
                                        <time dateTime={action.at_iso} className="text-xs text-gray-500">{action.at}</time>
                                    </div>
                                    <p className="mt-1.5 text-sm text-gray-700">
                                        {action.account ? (
                                            <Link href={route('admin.accounts.show', action.account.id)} className="font-semibold text-secondary hover:underline">
                                                {action.account.name}
                                            </Link>
                                        ) : (
                                            'Compte supprimé'
                                        )}
                                        {action.account && <span className="text-gray-500"> · {action.account.role_label}</span>}
                                        <span className="text-gray-500"> · par {action.admin}</span>
                                        {action.ends_at && <span className="text-gray-500"> · jusqu’au {action.ends_at}</span>}
                                    </p>
                                    {action.message && <p className="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">{action.message}</p>}
                                    {action.internal_note && (
                                        <p className="mt-2 rounded-lg bg-purple-50 px-3 py-2 text-sm text-purple-900">
                                            <span className="font-semibold">Note interne :</span> {action.internal_note}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}

                <Pagination paginator={actions} />
            </div>
        </DashboardLayout>
    );
}
