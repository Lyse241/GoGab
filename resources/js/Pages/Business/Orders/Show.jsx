import CourierCard from '@/Components/Business/CourierCard';
import DeliveryAnnouncement from '@/Components/Business/DeliveryAnnouncement';
import OrderActions from '@/Components/Business/OrderActions';
import Card, { CardHeader } from '@/Components/UI/Card';
import StatusBadge from '@/Components/UI/StatusBadge';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Button from '@/Components/UI/Button';
import { formatFCFA } from '@/utils/format';
import { Head, usePoll } from '@inertiajs/react';
import { ArrowLeft, History, MessageSquareText } from 'lucide-react';

function Row({ label, children }) {
    return (
        <div className="flex justify-between gap-4 py-2 text-sm">
            <dt className="text-gray-600">{label}</dt>
            <dd className="text-right font-medium text-gray-900">{children}</dd>
        </div>
    );
}

/**
 * Détail d'une commande pour l'entreprise : articles, client, livraison, paiement, actions et
 * historique complet (chaque changement de statut, son auteur, sa note). Rafraîchi toutes les 10 s.
 */
export default function Show({ order }) {
    usePoll(10000, { only: ['order'] });

    return (
        <DashboardLayout
            header={
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-xl font-bold text-secondary-900">Commande {order.number}</h1>
                    <StatusBadge status={order.status} />
                </div>
            }
            actions={
                <Button href={route('business.orders.index')} variant="ghost" size="sm" icon={ArrowLeft}>
                    Commandes
                </Button>
            }
        >
            <Head title={`Commande ${order.number}`} />

            <div className="mx-auto grid max-w-5xl gap-4 px-4 py-6 sm:px-6 lg:grid-cols-[1fr_20rem] lg:px-8">
                <div className="space-y-4">
                    <Card>
                        <CardHeader title="Articles" description={`Reçue le ${order.created_date} à ${order.created_at} · ${order.client}`} />
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
                            <Row label="Total payé par le client">{formatFCFA(order.total_price)}</Row>
                        </dl>
                        {order.client_note && (
                            <p className="mt-3 flex items-start gap-2 rounded-xl bg-accent-50 px-3 py-2 text-sm text-secondary-900 ring-1 ring-accent-200">
                                <MessageSquareText className="mt-0.5 h-4 w-4 shrink-0 text-accent-700" aria-hidden="true" />
                                {order.client_note}
                            </p>
                        )}
                        <DeliveryAnnouncement order={order} className="mt-4" />
                        <OrderActions order={order} className="mt-4 border-t border-gray-100 pt-4" />
                    </Card>

                    <Card>
                        <CardHeader title="Livraison et paiement" />
                        <dl className="divide-y divide-gray-100">
                            <Row label="Quartier">{order.neighborhood}</Row>
                            <Row label="Repères">
                                <span className="whitespace-pre-line font-normal">{order.address_landmarks}</span>
                            </Row>
                            <Row label="Paiement">{order.payment_method_label}</Row>
                            {order.change_due !== null && (
                                <Row label="Le client remettra">
                                    {formatFCFA(order.cash_given)} (monnaie : {formatFCFA(order.change_due)})
                                </Row>
                            )}
                            {order.cancel_reason && <Row label="Motif">{order.cancel_reason}</Row>}
                        </dl>
                    </Card>

                    {order.courier && (
                        <Card>
                            <CourierCard courier={order.courier} />
                        </Card>
                    )}
                </div>

                <Card className="self-start">
                    <CardHeader title="Historique" action={<History className="h-5 w-5 text-gray-400" aria-hidden="true" />} />
                    <ol className="relative space-y-4 border-l-2 border-gray-100 pl-5">
                        {order.history.map((entry) => (
                            <li key={entry.id} className="relative">
                                <span className="absolute -left-[1.6rem] top-1 h-3 w-3 rounded-full bg-primary-500 ring-4 ring-white" aria-hidden="true" />
                                <p className="text-sm font-semibold text-gray-900">{entry.label}</p>
                                <p className="text-xs text-gray-500">
                                    <time dateTime={entry.at_iso}>{entry.at}</time>
                                    {entry.author && ` · ${entry.author}${entry.author_role ? ` (${entry.author_role})` : ''}`}
                                </p>
                                {entry.note && <p className="mt-1 rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700">{entry.note}</p>}
                            </li>
                        ))}
                    </ol>
                </Card>
            </div>
        </DashboardLayout>
    );
}
