import { StoreThumb } from '@/Components/Cart/CartList';
import LazyImage from '@/Components/LazyImage';
import ReportProblemButton from '@/Components/Reports/ReportProblemButton';
import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import StatusBadge from '@/Components/UI/StatusBadge';
import Textarea from '@/Components/UI/Textarea';
import PublicLayout from '@/Layouts/PublicLayout';
import { cn } from '@/utils/cn';
import { formatFCFA, imageUrl } from '@/utils/format';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import { ArrowLeft, Bike, Check, MapPin, Phone, X } from 'lucide-react';
import { useEffect, useState } from 'react';

// Tant que la commande n'est pas terminée, la page se met à jour toute seule.
const REFRESH_INTERVAL = 10000;

function Row({ label, children }) {
    return (
        <div className="flex justify-between gap-4 py-2 text-sm">
            <dt className="text-gray-600">{label}</dt>
            <dd className="text-right font-medium text-gray-900">{children}</dd>
        </div>
    );
}

/**
 * Timeline verticale des étapes (depuis order_status_histories) : franchies (coche + heure),
 * en cours (mise en évidence), à venir (grisées), fin anticipée (refus / annulation).
 */
function Timeline({ steps }) {
    return (
        <ol className="relative">
            {steps.map((step, index) => {
                const last = index === steps.length - 1;
                const stopped = ['refusee', 'annulee'].includes(step.status);

                return (
                    <li key={step.status} className="relative flex gap-3 pb-5 last:pb-0" aria-current={step.state === 'current' ? 'step' : undefined}>
                        {!last && (
                            <span
                                className={cn('absolute left-[0.9rem] top-8 h-[calc(100%-1.75rem)] w-0.5', step.state === 'done' ? 'bg-primary-400' : 'bg-gray-200')}
                                aria-hidden="true"
                            />
                        )}
                        <span
                            className={cn(
                                'relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                                step.state === 'done' && 'bg-primary-600 text-white',
                                step.state === 'current' && !stopped && 'bg-primary-600 text-white ring-4 ring-primary-100',
                                step.state === 'current' && stopped && 'bg-danger-600 text-white ring-4 ring-danger-100',
                                step.state === 'upcoming' && 'bg-white text-gray-500 ring-2 ring-gray-200',
                            )}
                            aria-hidden="true"
                        >
                            {stopped ? (
                                <X className="h-4 w-4" />
                            ) : step.state === 'upcoming' ? (
                                <span className="h-2 w-2 rounded-full bg-gray-300" />
                            ) : step.state === 'current' ? (
                                <span className="h-2.5 w-2.5 animate-pulse rounded-full bg-white" />
                            ) : (
                                <Check className="h-4 w-4" />
                            )}
                        </span>
                        <div className="min-w-0 pt-1">
                            <p
                                className={cn(
                                    'text-sm',
                                    step.state === 'upcoming' ? 'text-gray-500' : 'font-semibold text-gray-900',
                                    step.state === 'current' && (stopped ? 'text-danger-700' : 'text-primary-800'),
                                )}
                            >
                                {step.label}
                                {step.state === 'current' && !stopped && <span className="sr-only"> (étape en cours)</span>}
                            </p>
                            {step.at && (
                                <time dateTime={step.at_iso} className="text-xs text-gray-500">
                                    {step.at}
                                </time>
                            )}
                            {step.note && <p className="mt-1 rounded-lg bg-danger-50 px-2.5 py-1.5 text-xs text-danger-800">Motif : {step.note}</p>}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

/**
 * Suivi d'une commande : timeline en direct, livreur (appel), annulation tant qu'elle est en
 * attente, récapitulatif (articles, totaux, adresse, paiement).
 */
export default function Show({ order, reporting }) {
    const [cancelOpen, setCancelOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [cancelling, setCancelling] = useState(false);

    // Rafraîchissement automatique des seules données de la commande, arrêté une fois terminée.
    const { start, stop } = usePoll(REFRESH_INTERVAL, { only: ['order'] }, { autoStart: !order.is_final });
    useEffect(() => {
        if (order.is_final) {
            stop();
        } else {
            start();
        }
        // start / stop sont recréés à chaque rendu : on ne réagit qu'au passage à « terminée »
        // (sinon chaque rendu relancerait le minuteur).
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [order.is_final]);

    const cancel = () =>
        router.put(
            route('orders.status.update', order.id),
            { status: 'annulee', note: reason.trim() || null },
            {
                preserveScroll: true,
                onStart: () => setCancelling(true),
                onFinish: () => {
                    setCancelling(false);
                    setCancelOpen(false);
                },
            },
        );

    return (
        <PublicLayout>
            <Head title={`Commande ${order.number}`} />

            <div className="mx-auto max-w-2xl space-y-4 pt-4">
                <Link href={route('orders.index')} className="inline-flex min-h-tap items-center gap-1.5 text-sm font-medium text-secondary hover:underline">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Mes commandes
                </Link>

                <div className="flex items-start gap-3">
                    <StoreThumb store={order.store} className="h-12 w-12" />
                    <div className="min-w-0 flex-1">
                        <h1 className="text-xl font-bold text-secondary-900">{order.store.name}</h1>
                        <p className="text-sm text-gray-500">
                            {order.number} · passée le {order.created_at}
                        </p>
                    </div>
                    <StatusBadge status={order.status} />
                </div>

                <Card>
                    <CardHeader
                        title="Suivi"
                        description={order.is_final ? undefined : 'Mis à jour automatiquement.'}
                    />
                    <Timeline steps={order.timeline} />

                    {order.can_cancel && (
                        <div className="mt-5 border-t border-gray-100 pt-4">
                            <Button variant="outline" icon={X} onClick={() => setCancelOpen(true)} className="text-danger-700">
                                Annuler la commande
                            </Button>
                            <p className="mt-1.5 text-xs text-gray-500">Possible tant que le commerce ne l’a pas acceptée.</p>
                        </div>
                    )}
                </Card>

                {order.courier && (
                    <Card>
                        <div className="flex items-center gap-3">
                            <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-secondary-50 text-secondary">
                                <Bike className="h-6 w-6" aria-hidden="true" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="font-semibold text-secondary-900">Votre livreur : {order.courier.first_name}</p>
                                {order.courier.vehicle && (
                                    <p className="text-sm text-gray-600">
                                        {order.courier.vehicle}
                                        {order.courier.vehicle_brand && ` · ${order.courier.vehicle_brand}`}
                                    </p>
                                )}
                            </div>
                            {order.courier.phone && (
                                <a
                                    href={`tel:${order.courier.phone.replace(/\s/g, '')}`}
                                    className="inline-flex h-11 shrink-0 items-center gap-2 rounded-full bg-primary-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                >
                                    <Phone className="h-4 w-4" aria-hidden="true" />
                                    Appeler
                                </a>
                            )}
                        </div>
                    </Card>
                )}

                <Card>
                    <CardHeader title="Livraison" action={<MapPin className="h-5 w-5 text-primary-600" aria-hidden="true" />} />
                    <dl className="divide-y divide-gray-100">
                        <Row label="Quartier">{order.neighborhood}</Row>
                        <Row label="Repères">
                            <span className="whitespace-pre-line font-normal">{order.address_landmarks}</span>
                        </Row>
                        <Row label="Paiement">{order.payment_method_label}</Row>
                        {order.change_due !== null && (
                            <>
                                <Row label="Vous remettez">{formatFCFA(order.cash_given)}</Row>
                                <Row label="Monnaie à recevoir">{formatFCFA(order.change_due)}</Row>
                            </>
                        )}
                        {order.client_note && (
                            <Row label="Note pour le commerce">
                                <span className="whitespace-pre-line font-normal">{order.client_note}</span>
                            </Row>
                        )}
                    </dl>
                </Card>

                <Card>
                    <CardHeader title="Récapitulatif" />
                    <ul className="divide-y divide-gray-100">
                        {order.items.map((item) => (
                            <li key={item.id} className="flex items-center gap-3 py-2.5">
                                <LazyImage src={imageUrl(item.image)} alt="" className="h-11 w-11 shrink-0 rounded-lg" />
                                <span className="min-w-0 flex-1 text-sm text-gray-800">
                                    <span className="font-semibold">{item.quantity} ×</span> {item.name}
                                </span>
                                <span className="shrink-0 text-sm font-medium text-gray-900">{formatFCFA(item.price * item.quantity)}</span>
                            </li>
                        ))}
                    </ul>
                    <dl className="mt-2 border-t border-gray-100 pt-2">
                        <Row label="Sous-total">{formatFCFA(order.subtotal)}</Row>
                        <Row label="Frais de livraison">{formatFCFA(order.delivery_fee)}</Row>
                        <div className="flex justify-between gap-4 border-t border-gray-100 pt-3">
                            <dt className="font-semibold text-gray-900">Total</dt>
                            <dd className="text-lg font-bold text-gray-900">{formatFCFA(order.total_price)}</dd>
                        </div>
                    </dl>
                </Card>

                {reporting && (
                    <div className="flex justify-center">
                        <ReportProblemButton reporting={reporting} className="text-gray-600" />
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={cancelOpen}
                onClose={() => setCancelOpen(false)}
                onConfirm={cancel}
                loading={cancelling}
                title={`Annuler la commande ${order.number} ?`}
                message={`${order.store.name} sera prévenu. Cette action est définitive.`}
                confirmLabel="Annuler la commande"
                cancelLabel="Garder ma commande"
            >
                <Textarea
                    id="cancel-reason"
                    label="Motif (facultatif)"
                    rows={2}
                    maxLength={500}
                    wrapperClassName="mt-3"
                    value={reason}
                    onChange={(event) => setReason(event.target.value)}
                />
            </ConfirmDialog>
        </PublicLayout>
    );
}
