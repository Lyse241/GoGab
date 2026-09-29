import Pagination from '@/Components/UI/Pagination';
import StatusBadge from '@/Components/UI/StatusBadge';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatFCFA } from "@/utils/format";
import { Head, Link } from "@inertiajs/react";
import { ChevronRight, UserCheck } from "lucide-react";

function StatCard({ label, value, hint }) {
    return (
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <p className="text-sm text-gray-500">{label}</p>
            <p className="mt-1 text-2xl font-bold text-secondary">{value}</p>
            {hint && <p className="mt-1 text-xs text-gray-500">{hint}</p>}
        </div>
    );
}

/**
 * Carte « À valider » : comptes en attente, lien vers la file de validation.
 */
function PendingAccountsCard({ count }) {
    return (
        <Link
            href={route("admin.accounts.index")}
            className={`group flex items-center gap-4 rounded-xl p-4 shadow-sm ring-1 transition hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary ${
                count > 0
                    ? "bg-accent-50 ring-accent-300"
                    : "bg-white ring-gray-200"
            }`}
        >
            <span
                className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-full ${
                    count > 0
                        ? "bg-accent text-secondary-900"
                        : "bg-gray-100 text-gray-500"
                }`}
            >
                <UserCheck className="h-6 w-6" aria-hidden="true" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-sm text-gray-600">À valider</span>
                <span className="block text-2xl font-bold text-secondary">
                    {count}
                </span>
                <span className="block text-xs text-gray-500">
                    {count > 0
                        ? `compte${count > 1 ? "s" : ""} en attente de validation`
                        : "Aucun compte en attente"}
                </span>
            </span>
            <ChevronRight
                className="h-5 w-5 text-gray-400 transition group-hover:translate-x-0.5"
                aria-hidden="true"
            />
        </Link>
    );
}

function FilterLink({ status, active, children }) {
    return (
        <Link
            href={route("admin.dashboard", status ? { status } : {})}
            preserveScroll
            preserveState
            className={`shrink-0 rounded-full border px-3 py-1.5 text-sm font-medium transition ${
                active
                    ? "border-secondary bg-secondary text-white"
                    : "border-gray-300 bg-white text-gray-700 hover:border-secondary"
            }`}
        >
            {children}
        </Link>
    );
}

export default function Dashboard({ stats, orders, filters }) {
    return (
        <DashboardLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-secondary">
                    Tableau de bord
                </h2>
            }
        >
            <Head title="Administration" />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
                {/* 1. Vue d'ensemble */}
                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <PendingAccountsCard count={stats.pending_accounts} />
                    <StatCard label="Commandes" value={stats.total_orders} />
                    <StatCard
                        label="Chiffre d'affaires total"
                        value={formatFCFA(stats.revenue)}
                        hint={`Dont ${formatFCFA(stats.delivered_revenue)} sur les commandes livrées`}
                    />
                    <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:col-span-2 lg:col-span-3">
                        <p className="text-sm text-gray-500">
                            Répartition par statut
                        </p>
                        <ul className="mt-2 grid gap-x-8 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
                            {stats.by_status.map((item) => {
                                const percent = stats.total_orders
                                    ? Math.round(
                                          (item.count / stats.total_orders) *
                                              100,
                                      )
                                    : 0;

                                return (
                                    <li key={item.value}>
                                        <div className="flex items-center justify-between text-sm">
                                            <StatusBadge
                                                status={item.value}
                                            />
                                            <span className="font-semibold text-gray-900">
                                                {item.count}
                                            </span>
                                        </div>
                                        <div className="mt-1 h-1.5 rounded-full bg-gray-100">
                                            <div
                                                className="h-1.5 rounded-full bg-primary-500"
                                                style={{ width: `${percent}%` }}
                                            />
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </section>

                {/* 2. Toutes les commandes */}
                <section>
                    <h3 className="text-lg font-semibold text-secondary">
                        Commandes ({orders.total})
                    </h3>

                    <div className="no-scrollbar -mx-4 mt-3 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:px-0">
                        <FilterLink active={!filters.status}>Toutes</FilterLink>
                        {stats.by_status.map((item) => (
                            <FilterLink
                                key={item.value}
                                status={item.value}
                                active={filters.status === item.value}
                            >
                                {item.label} ({item.count})
                            </FilterLink>
                        ))}
                    </div>

                    {orders.data.length === 0 ? (
                        <p className="mt-3 rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">
                            Aucune commande pour ce filtre.
                        </p>
                    ) : (
                        <>
                            {/* Mobile : une carte par commande */}
                            <ul className="mt-3 space-y-3 md:hidden">
                                {orders.data.map((order) => (
                                    <li
                                        key={order.id}
                                        className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <p className="font-semibold text-gray-900">
                                                    {order.number}
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    {order.created_at}
                                                </p>
                                            </div>
                                            <StatusBadge
                                                status={order.status}
                                            />
                                        </div>
                                        <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                            <div>
                                                <dt className="text-gray-500">
                                                    Client
                                                </dt>
                                                <dd className="text-gray-900">
                                                    {order.client}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-gray-500">
                                                    Boutique
                                                </dt>
                                                <dd className="text-gray-900">
                                                    {order.store ?? "—"}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-gray-500">
                                                    Quartier
                                                </dt>
                                                <dd className="text-gray-900">
                                                    {order.neighborhood}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-gray-500">
                                                    Livreur
                                                </dt>
                                                <dd className="text-gray-900">
                                                    {order.delivery ?? (
                                                        <span className="text-gray-400">
                                                            Non assignée
                                                        </span>
                                                    )}
                                                </dd>
                                            </div>
                                        </dl>
                                        <p className="mt-3 border-t border-gray-100 pt-2 text-right font-bold text-gray-900">
                                            {formatFCFA(order.total_price)}
                                        </p>
                                    </li>
                                ))}
                            </ul>

                            {/* Tablette et ordinateur : tableau */}
                            <div className="mt-3 hidden overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200 md:block">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Commande
                                            </th>
                                            <th className="px-4 py-3">
                                                Client
                                            </th>
                                            <th className="px-4 py-3">
                                                Boutique
                                            </th>
                                            <th className="px-4 py-3">
                                                Quartier
                                            </th>
                                            <th className="px-4 py-3">
                                                Livreur
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Total
                                            </th>
                                            <th className="px-4 py-3">
                                                Statut
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {orders.data.map((order) => (
                                            <tr
                                                key={order.id}
                                                className="whitespace-nowrap"
                                            >
                                                <td className="px-4 py-3">
                                                    <p className="font-medium text-gray-900">
                                                        {order.number}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        {order.created_at}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {order.client}
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {order.store ?? "—"}
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {order.neighborhood}
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {order.delivery ?? (
                                                        <span className="text-gray-400">
                                                            Non assignée
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right font-medium text-gray-900">
                                                    {formatFCFA(
                                                        order.total_price,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge
                                                        status={order.status}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}

                    <div className="mt-4">
                        <Pagination paginator={orders} />
                    </div>
                </section>
            </div>
        </DashboardLayout>
    );
}
