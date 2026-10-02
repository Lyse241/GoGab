import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import Modal from '@/Components/UI/Modal';
import StatusBadge from '@/Components/UI/StatusBadge';
import Textarea from '@/Components/UI/Textarea';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatFCFA } from '@/utils/format';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Ban, Bike, History, Hourglass, MessageSquareText, RotateCw, Store, UserRound } from 'lucide-react';
import { useState } from 'react';

function Row({ label, children }) {
    return (
        <div className="flex justify-between gap-4 py-2 text-sm">
            <dt className="text-gray-600">{label}</dt>
            <dd className="min-w-0 break-words text-right font-medium text-gray-900">{children}</dd>
        </div>
    );
}

function Party({ icon: Icon, title, person, extra, children }) {
    return (
        <Card padding="sm" className="px-4">
            <div className="flex items-start gap-3">
                <span className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-secondary-50 text-secondary">
                    <Icon className="h-5 w-5" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1 text-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">{title}</p>
                    {person ? (
                        <>
                            <Link href={route('admin.accounts.show', person.id)} className="font-semibold text-secondary-900 hover:underline">
                                {person.name}
                            </Link>
                            {person.phone && <p className="text-gray-700">{person.phone}</p>}
                            {person.email && <p className="truncate text-gray-500">{person.email}</p>}
                        </>
                    ) : (
                        !children && <p className="text-gray-500">—</p>
                    )}
                    {extra && <p className="text-gray-600">{extra}</p>}
                    {children}
                </div>
            </div>
        </Card>
    );
}

function CancelDialog({ order, onClose }) {
    const { data, setData, put, processing, errors } = useForm({ status: 'annulee', note: '' });

    const submit = (event) => {
        event.preventDefault();
        put(route('orders.status.update', order.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal
            open
            onClose={onClose}
            closeable={!processing}
            title={`Annuler la commande ${order.number}`}
            description="Le client, l’entreprise et le livreur éventuel sont prévenus avec ce motif."
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={processing}>
                        Retour
                    </Button>
                    <Button type="submit" form="cancel-order" variant="danger" icon={Ban} loading={processing} disabled={!data.note.trim()}>
                        Annuler la commande
                    </Button>
                </>
            }
        >
            <form id="cancel-order" onSubmit={submit} noValidate>
                <Textarea
                    id="note"
                    label="Motif de l’annulation"
                    required
                    rows={3}
                    maxLength={500}
                    placeholder="Ex. : commerce injoignable, aucun livreur disponible…"
                    value={data.note}
                    onChange={(event) => setData('note', event.target.value)}
                    error={errors.note}
                />
            </form>
        </Modal>
    );
}

/**
 * Détail d'une commande pour l'admin : parties, articles, paiement, historique complet des
 * statuts ; annulation (motif obligatoire) et relance de l'annonce.
 */
export default function Show({ order, parties, can, couriersInZone }) {
    const [cancelling, setCancelling] = useState(false);
    const [relaunching, setRelaunching] = useState(false);

    const relaunch = () =>
        router.post(
            route('admin.orders.relaunch', order.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setRelaunching(true),
                onFinish: () => setRelaunching(false),
            },
        );

    return (
        <DashboardLayout
            header={
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-bold text-secondary-900">Commande {order.number}</h1>
                    <StatusBadge status={order.status} />
                    {order.is_stuck && (
                        <Badge color="warning" icon={Hourglass}>
                            Bloquée · {order.searching_minutes} min
                        </Badge>
                    )}
                </div>
            }
            actions={
                <Button href={route('admin.orders.index')} variant="ghost" size="sm" icon={ArrowLeft}>
                    Commandes
                </Button>
            }
        >
            <Head title={`Commande ${order.number}`} />

            <div className="mx-auto grid max-w-6xl gap-4 px-4 py-6 sm:px-6 lg:grid-cols-[1fr_22rem] lg:px-8">
                <div className="space-y-4">
                    {(can.cancel || can.relaunch) && (
                        <Card>
                            <CardHeader title="Actions" />
                            {order.searching_minutes !== null && (
                                <p className="mb-3 text-sm text-gray-700">
                                    En recherche de livreur depuis {order.searching_minutes} min
                                    {order.announcement_count > 1 && ` · annonce envoyée ${order.announcement_count} fois`}
                                    {couriersInZone !== null && ` · ${couriersInZone} livreur${couriersInZone > 1 ? 's' : ''} disponible${couriersInZone > 1 ? 's' : ''} dans la zone`}.
                                </p>
                            )}
                            <div className="flex flex-wrap gap-2">
                                {can.relaunch && (
                                    <Button icon={RotateCw} variant={order.is_stuck ? 'primary' : 'outline'} loading={relaunching} onClick={relaunch}>
                                        Relancer l’annonce
                                    </Button>
                                )}
                                {can.cancel && (
                                    <Button icon={Ban} variant="danger" onClick={() => setCancelling(true)} disabled={relaunching}>
                                        Annuler la commande
                                    </Button>
                                )}
                            </div>
                        </Card>
                    )}

                    <Card>
                        <CardHeader title="Articles" description={`Passée le ${order.created_at}`} />
                        <ul className="divide-y divide-gray-100">
                            {order.items.map((item) => (
                                <li key={item.id} className="flex justify-between gap-3 py-2 text-sm">
                                    <span className="text-gray-800">
                                        <span className="font-semibold">{item.quantity} ×</span> {item.name}
                                    </span>
                                    <span className="font-medium text-gray-900">{formatFCFA(item.price * item.quantity)}</span>
                                </li>
                            ))}
                        </ul>
                        <dl className="mt-2 border-t border-gray-100 pt-2">
                            <Row label="Sous-total">{formatFCFA(order.subtotal)}</Row>
                            <Row label="Frais de livraison">{formatFCFA(order.delivery_fee)}</Row>
                            <Row label="Total">{formatFCFA(order.total_price)}</Row>
                            <Row label="Paiement">{order.payment_method_label}</Row>
                            {order.change_due !== null && (
                                <Row label="Le client remet">
                                    {formatFCFA(order.cash_given)} (monnaie : {formatFCFA(order.change_due)})
                                </Row>
                            )}
                            {order.cash_collected_at && <Row label="Encaissé le">{order.cash_collected_at}</Row>}
                            <Row label="Livraison">
                                {order.neighborhood} {order.zone && <span className="font-normal text-gray-500">({order.zone})</span>}
                            </Row>
                            <Row label="Repères">
                                <span className="whitespace-pre-line font-normal">{order.address_landmarks}</span>
                            </Row>
                            {order.cancel_reason && <Row label="Motif d’arrêt">{order.cancel_reason}</Row>}
                        </dl>
                        {order.client_note && (
                            <p className="mt-3 flex items-start gap-2 rounded-xl bg-accent-50 px-3 py-2 text-sm text-secondary-900 ring-1 ring-accent-200">
                                <MessageSquareText className="mt-0.5 h-4 w-4 shrink-0 text-accent-700" aria-hidden="true" />
                                {order.client_note}
                            </p>
                        )}
                    </Card>

                    <Card>
                        <CardHeader title="Historique des statuts" action={<History className="h-5 w-5 text-gray-400" aria-hidden="true" />} />
                        <ol className="relative space-y-4 border-l-2 border-gray-100 pl-5">
                            {order.history.map((entry) => (
                                <li key={entry.id} className="relative">
                                    <span className="absolute -left-[1.6rem] top-1 h-3 w-3 rounded-full bg-primary-500 ring-4 ring-white" aria-hidden="true" />
                                    <p className="text-sm font-semibold text-gray-900">{entry.label}</p>
                                    <p className="text-xs text-gray-500">
                                        {entry.at}
                                        {entry.author && ` · ${entry.author}${entry.author_role ? ` (${entry.author_role})` : ''}`}
                                    </p>
                                    {entry.note && <p className="mt-1 rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700">{entry.note}</p>}
                                </li>
                            ))}
                        </ol>
                    </Card>
                </div>

                <div className="space-y-4">
                    <Party icon={UserRound} title="Client" person={parties.client} />
                    <Party
                        icon={Store}
                        title="Commerce"
                        person={parties.store?.owner}
                        extra={parties.store && `${parties.store.name}${parties.store.neighborhood ? ` · ${parties.store.neighborhood} (${parties.store.zone})` : ''}`}
                    >
                        {parties.store && !parties.store.owner && <p className="text-gray-500">Commerce sans compte entreprise</p>}
                    </Party>
                    <Party icon={Bike} title="Livreur" person={parties.courier} extra={parties.courier?.vehicle} />
                </div>
            </div>

            {cancelling && <CancelDialog order={order} onClose={() => setCancelling(false)} />}
        </DashboardLayout>
    );
}
