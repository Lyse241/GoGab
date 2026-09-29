import StatusBadge from '@/Components/UI/StatusBadge';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatFCFA, imageUrl } from '@/utils/format';
import { Head, Link } from '@inertiajs/react';

function Detail({ label, children }) {
    return (
        <div className="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt className="text-sm text-gray-500">{label}</dt>
            <dd className="text-sm font-medium text-gray-900 sm:col-span-2">
                {children}
            </dd>
        </div>
    );
}

export default function Show({ order }) {
    return (
        <PublicLayout>
            <Head title={`Commande ${order.number}`} />

            <div className="mx-auto max-w-2xl">
                <div className="mt-6 text-center">
                    <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-100">
                        <svg
                            className="h-8 w-8 text-primary-600"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            strokeWidth="2.5"
                            aria-hidden="true"
                        >
                            <path strokeLinecap="round" strokeLinejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <h1 className="mt-3 text-2xl font-bold text-secondary">
                        Suivi de ma commande
                    </h1>
                    <p className="mt-1 text-gray-600">
                        Commande{' '}
                        <span className="font-semibold text-gray-900">{order.number}</span>{' '}
                        · passée le {order.created_at}
                    </p>
                    <StatusBadge
                        status={order.status}
                        size="lg"
                        className="mt-3"
                    />
                </div>

                <section className="mt-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <h2 className="font-semibold text-gray-900">Livraison</h2>
                    <dl className="mt-2 divide-y divide-gray-100">
                        <Detail label="Quartier">{order.neighborhood}</Detail>
                        <Detail label="Repères">
                            <span className="whitespace-pre-line">{order.address_landmarks}</span>
                        </Detail>
                        <Detail label="Paiement">{order.payment_method_label}</Detail>
                        {order.change_due !== null && (
                            <Detail label="Montant remis">
                                {formatFCFA(order.cash_given)} · monnaie à rendre : {formatFCFA(order.change_due)}
                            </Detail>
                        )}
                        {order.client_note && (
                            <Detail label="Note pour le commerce">
                                <span className="whitespace-pre-line">{order.client_note}</span>
                            </Detail>
                        )}
                        {order.cancel_reason && <Detail label="Motif">{order.cancel_reason}</Detail>}
                    </dl>
                </section>

                <section id="suivi" className="mt-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <h2 className="font-semibold text-gray-900">Suivi</h2>
                    <ol className="relative mt-3 space-y-3 border-l-2 border-gray-100 pl-5">
                        {order.history.map((step, index) => (
                            <li key={index} className="relative">
                                <span className="absolute -left-[1.6rem] top-1 h-3 w-3 rounded-full bg-primary-500 ring-4 ring-white" aria-hidden="true" />
                                <p className="text-sm font-semibold text-gray-900">{step.label}</p>
                                <p className="text-xs text-gray-500">{step.at}</p>
                            </li>
                        ))}
                    </ol>
                </section>

                <section className="mt-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <h2 className="font-semibold text-gray-900">
                        Récapitulatif{order.store && ` · ${order.store}`}
                    </h2>
                    <ul className="mt-2 divide-y divide-gray-100">
                        {order.items.map((item) => (
                            <li key={item.id} className="flex items-center gap-3 py-3">
                                <div className="h-12 w-12 shrink-0 overflow-hidden rounded-md bg-gray-200">
                                    {item.image && (
                                        <img
                                            src={imageUrl(item.image)}
                                            alt=""
                                            loading="lazy"
                                            className="h-full w-full object-cover"
                                        />
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium text-gray-900">{item.name}</p>
                                    <p className="text-sm text-gray-500">
                                        {item.quantity} × {formatFCFA(item.price)}
                                    </p>
                                </div>
                                <p className="shrink-0 font-medium text-gray-900">
                                    {formatFCFA(item.price * item.quantity)}
                                </p>
                            </li>
                        ))}
                    </ul>
                    <div className="flex items-center justify-between border-t border-gray-200 pt-3 text-sm">
                        <span className="text-gray-600">Sous-total</span>
                        <span className="font-medium text-gray-900">{formatFCFA(order.subtotal)}</span>
                    </div>
                    <div className="flex items-center justify-between pt-1 text-sm">
                        <span className="text-gray-600">Frais de livraison</span>
                        <span className="font-medium text-gray-900">{formatFCFA(order.delivery_fee)}</span>
                    </div>
                    <div className="mt-2 flex items-center justify-between border-t border-gray-200 pt-3">
                        <span className="font-medium text-gray-700">Total</span>
                        <span className="text-lg font-bold text-gray-900">
                            {formatFCFA(order.total_price)}
                        </span>
                    </div>
                </section>

                <Link
                    href={route('home')}
                    className="mt-6 block w-full rounded-full bg-primary-600 py-3 text-center font-semibold text-white hover:bg-primary-700"
                >
                    Retour aux boutiques
                </Link>
            </div>
        </PublicLayout>
    );
}
