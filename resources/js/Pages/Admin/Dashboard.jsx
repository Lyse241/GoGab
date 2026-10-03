import OrdersWeekChart from '@/Components/Admin/OrdersWeekChart';
import Card, { CardHeader } from '@/Components/UI/Card';
import StatusBadge from '@/Components/UI/StatusBadge';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { cn } from '@/utils/cn';
import { formatFCFA } from '@/utils/format';
import { Head, Link } from '@inertiajs/react';
import { Bike, ChevronRight, ClipboardList, Hourglass, ReceiptText, Siren, Store, UserCheck, UserPlus, Wallet } from 'lucide-react';

/**
 * Carte chiffrée ; `href` la rend cliquable, `highlight` la met en avant (quelque chose à faire).
 */
function StatCard({ icon: Icon, label, value, hint, href, highlight }) {
    const content = (
        <>
            <span
                className={cn(
                    'flex h-11 w-11 shrink-0 items-center justify-center rounded-full',
                    highlight ? 'bg-accent text-secondary-900' : 'bg-primary-50 text-primary-700',
                )}
            >
                <Icon className="h-5 w-5" aria-hidden="true" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm text-gray-600">{label}</span>
                <span className="block truncate text-2xl font-bold text-secondary-900">{value}</span>
                {hint && <span className="block truncate text-xs text-gray-500">{hint}</span>}
            </span>
            {href && <ChevronRight className="h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" />}
        </>
    );
    const classes = cn('flex min-w-0 items-center gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1', highlight ? 'bg-accent-50 ring-accent-300' : 'ring-gray-200');

    return href ? (
        <Link href={href} className={cn(classes, 'transition hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary')}>
            {content}
        </Link>
    ) : (
        <div className={classes}>{content}</div>
    );
}

const EVENT_ICONS = { order: ReceiptText, account: UserPlus, report: Siren };

/**
 * Tableau de bord admin : chiffres du jour (heure de Libreville), commandes des 7 derniers
 * jours, répartition par statut, derniers événements.
 */
export default function Dashboard({ stats, week, byStatus, events, stuckMinutes }) {
    const totalByStatus = byStatus.reduce((sum, item) => sum + item.count, 0);
    const maxByStatus = Math.max(1, ...byStatus.map((item) => item.count));

    return (
        <DashboardLayout header={<h1 className="text-xl font-bold text-secondary-900">Tableau de bord</h1>}>
            <Head title="Administration" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <section aria-label="Chiffres clés" className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <StatCard
                        icon={UserCheck}
                        label="Comptes à valider"
                        value={stats.pending_accounts}
                        href={route('admin.accounts.index')}
                        highlight={stats.pending_accounts > 0}
                    />
                    <StatCard
                        icon={Siren}
                        label="Signalements à traiter"
                        value={stats.open_reports}
                        href={route('admin.reports.index')}
                        highlight={stats.open_reports > 0}
                    />
                    <StatCard icon={ClipboardList} label="Commandes du jour" value={stats.orders_today} hint={`${stats.total_orders} au total`} href={route('admin.orders.index')} />
                    <StatCard icon={Wallet} label="Chiffre d’affaires du jour" value={formatFCFA(stats.revenue_today)} hint="Commandes livrées aujourd’hui, frais compris" />
                    <StatCard icon={Bike} label="Livreurs disponibles" value={stats.available_couriers} href={route('admin.deliveries.index')} />
                    <StatCard icon={Store} label="Commerces actifs" value={stats.active_stores} hint="Visibles par les clients" href={route('admin.stores.index')} />
                </section>

                {stats.stuck_orders > 0 && (
                    <Link
                        href={route('admin.orders.index', { status: 'stuck' })}
                        className="flex items-center gap-3 rounded-2xl bg-warning-50 px-4 py-3 text-sm text-warning-900 ring-1 ring-warning-200 hover:bg-warning-100"
                    >
                        <Hourglass className="h-5 w-5 shrink-0" aria-hidden="true" />
                        <span className="flex-1">
                            <span className="font-semibold">
                                {stats.stuck_orders} commande{stats.stuck_orders > 1 ? 's' : ''} sans livreur
                            </span>{' '}
                            depuis plus de {stuckMinutes} minutes : relancez l’annonce ou annulez.
                        </span>
                        <ChevronRight className="h-5 w-5 shrink-0" aria-hidden="true" />
                    </Link>
                )}

                <div className="grid gap-4 lg:grid-cols-[3fr_2fr]">
                    <Card>
                        <CardHeader title="Commandes des 7 derniers jours" description="Commandes passées par jour (heure de Libreville)" />
                        <OrdersWeekChart data={week} />
                    </Card>

                    <Card>
                        <CardHeader title="Répartition par statut" description={`${totalByStatus} commande${totalByStatus > 1 ? 's' : ''} au total`} />
                        <ul className="space-y-1">
                            {byStatus.map((item) => (
                                <li key={item.value}>
                                    <Link href={route('admin.orders.index', { status: item.value })} className="group block min-h-tap rounded-lg py-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                        <span className="flex items-center justify-between gap-2 text-sm">
                                            <StatusBadge status={item.value} size="sm" />
                                            <span className="font-semibold tabular-nums text-gray-900 group-hover:underline">{item.count}</span>
                                        </span>
                                        <span className="mt-1 block h-1.5 rounded-full bg-gray-100">
                                            <span className="block h-1.5 rounded-full bg-secondary-600" style={{ width: `${(item.count / maxByStatus) * 100}%` }} />
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>

                <Card>
                    <CardHeader title="Derniers événements" />
                    {events.length === 0 ? (
                        <p className="text-sm text-gray-500">Rien pour le moment.</p>
                    ) : (
                        <ul className="-mx-2 divide-y divide-gray-100">
                            {events.map((event) => {
                                const Icon = EVENT_ICONS[event.type] ?? ReceiptText;
                                const body = (
                                    <>
                                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-600">
                                            <Icon className="h-4 w-4" aria-hidden="true" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium text-gray-900">{event.title}</span>
                                            {event.detail && <span className="block truncate text-xs text-gray-500">{event.detail}</span>}
                                        </span>
                                        <span className="shrink-0 text-xs text-gray-500">{event.at}</span>
                                    </>
                                );

                                return (
                                    <li key={event.key}>
                                        {event.url ? (
                                            <Link href={event.url} className="flex items-center gap-3 rounded-lg px-2 py-2.5 hover:bg-gray-50">
                                                {body}
                                            </Link>
                                        ) : (
                                            <div className="flex items-center gap-3 px-2 py-2.5">{body}</div>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </Card>
            </div>
        </DashboardLayout>
    );
}
