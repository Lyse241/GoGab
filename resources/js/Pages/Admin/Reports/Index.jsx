import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/UI/Pagination';
import Select from '@/Components/UI/Select';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { cn } from '@/utils/cn';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight, Flag, ShieldCheck, Siren } from 'lucide-react';

/**
 * Signalements des utilisateurs : filtres (statut, motif, type de compte signalé), fraudes à
 * traiter en tête avec le badge « Urgent ». Chaque ligne mène au détail.
 */
export default function Index({ reports, filters, statuses, reasons, types }) {
    const filter = (key, value) =>
        router.get(
            route('admin.reports.index'),
            Object.fromEntries(Object.entries({ ...filters, [key]: value || null }).filter(([k, v]) => v && !(k === 'status' && v === 'pending'))),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <DashboardLayout header={<h1 className="text-xl font-bold text-secondary-900">Signalements</h1>}>
            <Head title="Signalements" />

            <div className="mx-auto max-w-5xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                <div className="grid gap-3 sm:grid-cols-3">
                    <Select id="filter-status" label="Statut" options={statuses} value={filters.status ?? 'pending'} onChange={(e) => filter('status', e.target.value)} />
                    <Select id="filter-reason" label="Motif" placeholder="Tous les motifs" options={reasons} value={filters.reason ?? ''} onChange={(e) => filter('reason', e.target.value)} />
                    <Select id="filter-type" label="Compte signalé" placeholder="Tous les comptes" options={types} value={filters.type ?? ''} onChange={(e) => filter('type', e.target.value)} />
                </div>

                {reports.data.length === 0 ? (
                    <EmptyState icon={ShieldCheck} title="Aucun signalement" description="Rien ne correspond à ces filtres." />
                ) : (
                    <Card padding="none">
                        <ul className="divide-y divide-gray-100">
                            {reports.data.map((report) => (
                                <li key={report.id}>
                                    <Link
                                        href={route('admin.reports.show', report.id)}
                                        className={cn(
                                            'flex items-start gap-3 p-4 transition hover:bg-gray-50 focus:outline-none focus-visible:bg-gray-50',
                                            report.is_urgent && 'bg-danger-50/50',
                                        )}
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                {report.is_urgent && (
                                                    <Badge color="danger" size="sm" icon={Siren}>
                                                        Urgent
                                                    </Badge>
                                                )}
                                                <Badge color={report.status_color} size="sm">
                                                    {report.status_label}
                                                </Badge>
                                                <span className="text-sm font-semibold text-gray-900">{report.reason_label}</span>
                                                <span className="text-xs text-gray-500">{report.at}</span>
                                            </div>
                                            <p className="mt-1 text-sm text-gray-700">
                                                <span className="font-semibold text-secondary-900">{report.reported.name}</span>
                                                <span className="text-gray-500"> ({report.reported.role_label})</span>
                                                {report.reported.is_flagged && <Flag className="ml-1 inline h-3.5 w-3.5 text-danger-600" aria-label="Compte signalé en interne" />}
                                                <span className="text-gray-500"> · par {report.reporter?.name ?? 'compte supprimé'}</span>
                                                {report.order && <span className="text-gray-500"> · {report.order.number}</span>}
                                            </p>
                                            <p className="mt-1 line-clamp-2 text-sm text-gray-600">{report.excerpt}</p>
                                        </div>
                                        <ChevronRight className="mt-1 h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}

                <Pagination paginator={reports} />
            </div>
        </DashboardLayout>
    );
}
