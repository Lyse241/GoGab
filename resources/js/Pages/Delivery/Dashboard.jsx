import Spinner from '@/Components/UI/Spinner';
import StatusBadge from '@/Components/UI/StatusBadge';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatFCFA } from '@/utils/format';
import { Head, router, usePoll } from '@inertiajs/react';
import { useState } from 'react';

const REFRESH_INTERVAL = 30000; // 30 s : nouvelles commandes sans recharger la page

// Libellé du bouton selon l'étape suivante (proposée par le serveur, OrderWorkflow).
const nextStepLabels = {
    en_livraison: 'J’ai récupéré la commande',
    arrive: 'Je suis arrivé chez le client',
    livree: 'Commande remise au client',
};

function OrderCard({ order, children }) {
    const itemCount = order.items.reduce((sum, item) => sum + item.quantity, 0);

    return (
        <article className="flex flex-col rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="font-semibold text-gray-900">{order.number}</p>
                    <p className="text-xs text-gray-500">Passée le {order.created_at}</p>
                </div>
                <p className="text-lg font-bold text-gray-900">
                    {formatFCFA(order.total_price)}
                </p>
            </div>

            {order.status !== 'en_recherche_livreur' && (
                <StatusBadge
                    status={order.status}
                    className="mt-2 self-start"
                />
            )}

            <dl className="mt-3 space-y-2 text-sm">
                <div>
                    <dt className="text-gray-500">Récupérer chez</dt>
                    <dd className="font-medium text-gray-900">
                        {order.store ?? '—'}
                        {order.store_neighborhood && <span className="font-normal text-gray-500"> · {order.store_neighborhood}</span>}
                    </dd>
                </div>
                <div>
                    <dt className="text-gray-500">Livrer à</dt>
                    <dd className="font-medium text-gray-900">{order.neighborhood}</dd>
                    <dd className="whitespace-pre-line text-gray-700">
                        {order.address_landmarks}
                    </dd>
                </div>
                <div className="flex flex-wrap gap-x-6 gap-y-2">
                    <div>
                        <dt className="text-gray-500">Paiement</dt>
                        <dd className="font-medium text-gray-900">
                            {order.payment_method_label}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-gray-500">Articles</dt>
                        <dd className="font-medium text-gray-900">{itemCount}</dd>
                    </div>
                </div>
                {order.client && (
                    <div>
                        <dt className="text-gray-500">Client</dt>
                        <dd className="font-medium text-gray-900">
                            {order.client.name}
                            {order.client.phone && (
                                <>
                                    {' · '}
                                    <a
                                        href={`tel:${order.client.phone.replace(/\s/g, '')}`}
                                        className="text-secondary underline"
                                    >
                                        {order.client.phone}
                                    </a>
                                </>
                            )}
                        </dd>
                    </div>
                )}
            </dl>

            <details className="mt-3 text-sm">
                <summary className="cursor-pointer text-gray-600 hover:text-gray-900">
                    Détail des articles
                </summary>
                <ul className="mt-2 space-y-1 text-gray-700">
                    {order.items.map((item) => (
                        <li key={item.id}>
                            {item.quantity} × {item.name}
                        </li>
                    ))}
                </ul>
            </details>

            <div className="mt-4 pt-1 sm:mt-auto">{children}</div>
        </article>
    );
}

function ActionButton({ onClick, busy, variant = 'primary', children }) {
    const styles = {
        primary: 'bg-primary-600 hover:bg-primary-700 text-white',
        dark: 'bg-secondary hover:bg-secondary-800 text-white',
    };

    return (
        <button
            type="button"
            onClick={onClick}
            disabled={busy}
            aria-busy={busy}
            className={`inline-flex w-full items-center justify-center gap-2 rounded-full py-2.5 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:opacity-50 ${styles[variant]}`}
        >
            {busy ? (
                <>
                    <Spinner /> Envoi…
                </>
            ) : (
                children
            )}
        </button>
    );
}

function EmptyState({ children }) {
    return (
        <p className="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">
            {children}
        </p>
    );
}

export default function Dashboard({ available, mine, deliveredToday, zone, isAvailable }) {
    // Identifiant de la commande en cours d'envoi (bloque les doubles clics).
    const [busyId, setBusyId] = useState(null);

    usePoll(REFRESH_INTERVAL, { only: ['available', 'mine', 'deliveredToday'] });

    const options = {
        preserveScroll: true,
        onFinish: () => setBusyId(null),
    };

    const accept = (order) => {
        setBusyId(order.id);
        router.post(route('delivery.orders.accept', order.id), {}, options);
    };

    const advance = (order) => {
        setBusyId(order.id);
        router.put(
            route('orders.status.update', order.id),
            { status: order.next_status },
            options,
        );
    };

    return (
        <DashboardLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-secondary">
                        Mes livraisons
                    </h2>
                    <p className="text-sm text-gray-600">
                        Livrées aujourd'hui :{' '}
                        <span className="font-semibold text-gray-900">{deliveredToday}</span>
                    </p>
                </div>
            }
        >
            <Head title="Espace livreur" />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
                <section>
                    <h3 className="mb-3 text-lg font-semibold text-secondary">
                        Mes livraisons en cours ({mine.length})
                    </h3>
                    {mine.length === 0 ? (
                        <EmptyState>
                            Aucune livraison en cours. Acceptez une commande
                            ci-dessous pour commencer.
                        </EmptyState>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {mine.map((order) => (
                                <OrderCard key={order.id} order={order}>
                                    <ActionButton
                                        variant="dark"
                                        busy={busyId === order.id}
                                        onClick={() => advance(order)}
                                    >
                                        {nextStepLabels[order.next_status]}
                                    </ActionButton>
                                </OrderCard>
                            ))}
                        </div>
                    )}
                </section>

                <section>
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <h3 className="text-lg font-semibold text-secondary">
                            Courses à prendre{zone ? ` · zone ${zone}` : ''} ({available.length})
                        </h3>
                        <button
                            type="button"
                            onClick={() =>
                                router.reload({
                                    only: ['available', 'mine', 'deliveredToday'],
                                })
                            }
                            className="text-sm font-medium text-secondary hover:underline"
                        >
                            Actualiser
                        </button>
                    </div>
                    {available.length === 0 ? (
                        <EmptyState>
                            {!zone
                                ? 'Aucune zone de livraison n’est rattachée à votre profil : contactez l’équipe Gogab.'
                                : !isAvailable
                                  ? 'Vous êtes indisponible : aucune course ne vous est proposée.'
                                  : `Aucune course à prendre dans la zone ${zone} pour le moment. La liste se met à jour automatiquement.`}
                        </EmptyState>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {available.map((order) => (
                                <OrderCard key={order.id} order={order}>
                                    <ActionButton
                                        busy={busyId === order.id}
                                        onClick={() => accept(order)}
                                    >
                                        Accepter
                                    </ActionButton>
                                </OrderCard>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </DashboardLayout>
    );
}
