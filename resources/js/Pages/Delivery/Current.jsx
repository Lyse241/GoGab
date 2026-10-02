import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import Checkbox from '@/Components/UI/Checkbox';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import EmptyState from '@/Components/UI/EmptyState';
import Modal from '@/Components/UI/Modal';
import Stepper from '@/Components/UI/Stepper';
import DeliveryLayout from '@/Layouts/DeliveryLayout';
import { cn } from '@/utils/cn';
import { formatFCFA } from '@/utils/format';
import { Head, router, usePoll } from '@inertiajs/react';
import { Banknote, Bike, CheckCircle2, HandCoins, MapPin, Megaphone, MessageSquareText, Package, PackageCheck, Phone, Store, UserRound } from 'lucide-react';
import { useState } from 'react';

const STEPS = [{ label: 'Retrait au commerce' }, { label: 'En route' }, { label: 'Chez le client' }, { label: 'Livrée' }];
const STEP_INDEX = { livreur_assigne: 0, en_livraison: 1, arrive: 2, livree: 3 };

// Action unique de chaque étape (l'étape suivante vient du serveur : OrderWorkflow).
const ACTIONS = {
    en_livraison: { label: 'J’ai récupéré la commande', icon: Package, hint: (order) => `Rendez-vous chez ${order.store.name} pour récupérer la commande.` },
    arrive: { label: 'Je suis arrivé chez le client', icon: MapPin, hint: (order) => `Livrez ${order.client.first_name} à ${order.client.neighborhood}.` },
    livree: { label: 'Commande remise', icon: PackageCheck, hint: (order) => `Remettez la commande à ${order.client.first_name}${order.is_cash ? ' et encaissez le paiement' : ''}.` },
};

function CallButton({ phone, name }) {
    if (!phone) {
        return null;
    }

    return (
        <a
            href={`tel:${phone.replace(/\s/g, '')}`}
            className="inline-flex min-h-tap shrink-0 items-center gap-2 rounded-full bg-primary-600 px-4 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
            aria-label={`Appeler ${name}`}
        >
            <Phone className="h-4 w-4" aria-hidden="true" />
            Appeler
        </a>
    );
}

function Party({ icon: Icon, title, name, neighborhood, landmarks, phone, highlight }) {
    return (
        <Card padding="sm" className={cn('px-4', highlight && 'ring-2 ring-primary-300')}>
            <div className="flex items-start gap-3">
                <span className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-secondary-50 text-secondary">
                    <Icon className="h-5 w-5" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">{title}</p>
                    <p className="truncate font-bold text-secondary-900">{name}</p>
                    {neighborhood && (
                        <p className="flex items-center gap-1 text-sm font-medium text-gray-800">
                            <MapPin className="h-3.5 w-3.5 text-primary-600" aria-hidden="true" />
                            {neighborhood}
                        </p>
                    )}
                    {landmarks && <p className="mt-0.5 whitespace-pre-line text-sm text-gray-600">{landmarks}</p>}
                </div>
                <CallButton phone={phone} name={name} />
            </div>
        </Card>
    );
}

/**
 * Encaissement toujours visible : montant à encaisser, montant remis par le client, monnaie à rendre.
 */
function CashPanel({ order }) {
    if (!order.is_cash) {
        return (
            <p className="flex items-center gap-2 rounded-2xl bg-gray-100 px-4 py-3 text-sm text-gray-700">
                <Banknote className="h-5 w-5 shrink-0 text-gray-500" aria-hidden="true" />
                {order.payment_method_label} · rien à encaisser en espèces ({formatFCFA(order.total_price)}).
            </p>
        );
    }

    return (
        <div className="rounded-2xl bg-accent-50 p-4 ring-1 ring-accent-300">
            <p className="flex items-center gap-2 text-sm font-semibold text-secondary-900">
                <HandCoins className="h-5 w-5 text-accent-700" aria-hidden="true" />
                Paiement à la livraison
            </p>
            <dl className="mt-3 grid grid-cols-3 gap-2 text-center">
                <div className="rounded-xl bg-white px-2 py-2">
                    <dt className="text-[11px] font-medium text-gray-500">À encaisser</dt>
                    <dd className="text-base font-bold text-secondary-900">{formatFCFA(order.total_price)}</dd>
                </div>
                <div className="rounded-xl bg-white px-2 py-2">
                    <dt className="text-[11px] font-medium text-gray-500">Le client remet</dt>
                    <dd className="text-base font-bold text-secondary-900">{order.cash_given !== null ? formatFCFA(order.cash_given) : '—'}</dd>
                </div>
                <div className="rounded-xl bg-secondary px-2 py-2 text-white">
                    <dt className="text-[11px] font-medium text-secondary-100">Monnaie à rendre</dt>
                    <dd className="text-base font-bold">{order.change_due !== null ? formatFCFA(order.change_due) : '—'}</dd>
                </div>
            </dl>
        </div>
    );
}

/**
 * Écran principal du livreur : la course en cours, étape par étape, avec un seul gros bouton
 * (en bas, à portée de pouce). Récupération et remise demandent une confirmation ; en cash, la
 * remise exige de cocher « Montant encaissé » (le serveur le revérifie).
 */
export default function Current({ order }) {
    const [confirming, setConfirming] = useState(false);
    const [cashCollected, setCashCollected] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    // Annulation par l'admin, etc. : l'écran suit l'état réel de la course.
    usePoll(15000, { only: ['order', 'badges'] });

    if (!order) {
        return (
            <DeliveryLayout title="Course en cours">
                <Head title="Course en cours" />
                <EmptyState
                    icon={Bike}
                    title="Aucune course en cours"
                    description="Acceptez une offre de votre zone pour démarrer une course."
                    action={
                        <Button href={route('delivery.offers')} icon={Megaphone}>
                            Voir les offres
                        </Button>
                    }
                />
            </DeliveryLayout>
        );
    }

    const action = ACTIONS[order.next_status];
    const step = STEP_INDEX[order.status] ?? 0;

    const send = () =>
        router.put(
            route('orders.status.update', order.id),
            { status: order.next_status, cash_collected: order.next_status === 'livree' && order.is_cash ? cashCollected : undefined },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onSuccess: () => {
                    setConfirming(false);
                    setCashCollected(false);
                },
                onError: (errors) => setError(errors.cash_collected ?? errors.status ?? null),
            },
        );

    // Récupération et remise : confirmation ; arrivée : un seul geste.
    const start = () => {
        setError(null);
        if (order.next_status === 'arrive') {
            send();
        } else {
            setConfirming(true);
        }
    };

    return (
        <DeliveryLayout title={`Course ${order.number}`} subtitle={`Gain : ${formatFCFA(order.earning)}`} availability={false}>
            <Head title="Course en cours" />

            <Card padding="sm" className="px-4">
                <Stepper steps={STEPS} current={step} />
            </Card>

            {action && <p className="rounded-2xl bg-primary-50 px-4 py-3 text-sm font-medium text-primary-900 ring-1 ring-primary-200">{action.hint(order)}</p>}

            <CashPanel order={order} />

            <Party
                icon={Store}
                title="Commerce"
                name={order.store.name}
                neighborhood={order.store.neighborhood}
                landmarks={order.store.address_landmarks}
                phone={order.store.phone}
                highlight={order.status === 'livreur_assigne'}
            />
            <Party
                icon={UserRound}
                title="Client"
                name={order.client.first_name}
                neighborhood={order.client.neighborhood}
                landmarks={order.client.address_landmarks}
                phone={order.client.phone}
                highlight={order.status !== 'livreur_assigne'}
            />

            <Card padding="sm" className="px-4">
                <p className="mb-2 flex items-center gap-2 text-sm font-semibold text-secondary-900">
                    <Package className="h-4 w-4 text-gray-500" aria-hidden="true" />
                    {order.item_count} article{order.item_count > 1 ? 's' : ''}
                </p>
                <ul className="divide-y divide-gray-100 text-sm">
                    {order.items.map((item) => (
                        <li key={item.id} className="py-1.5 text-gray-800">
                            <span className="font-semibold">{item.quantity} ×</span> {item.name}
                        </li>
                    ))}
                </ul>
                {order.client_note && (
                    <p className="mt-2 flex items-start gap-2 rounded-xl bg-accent-50 px-3 py-2 text-sm text-secondary-900 ring-1 ring-accent-200">
                        <MessageSquareText className="mt-0.5 h-4 w-4 shrink-0 text-accent-700" aria-hidden="true" />
                        {order.client_note}
                    </p>
                )}
            </Card>

            {/* Espace pour la barre d'action fixe. */}
            <div className="h-20" aria-hidden="true" />

            {/* Gros bouton unique, au-dessus de la barre de navigation (mobile). */}
            {action && (
                <div className="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-20 border-t border-gray-100 bg-white/95 px-4 py-3 backdrop-blur lg:bottom-0 lg:left-64">
                    <div className="mx-auto max-w-2xl">
                        <Button fullWidth size="lg" variant="secondary" icon={action.icon} loading={busy && !confirming} disabled={busy} onClick={start}>
                            {action.label}
                        </Button>
                    </div>
                </div>
            )}

            {/* Récupération */}
            <ConfirmDialog
                open={confirming && order.next_status === 'en_livraison'}
                onClose={() => !busy && setConfirming(false)}
                onConfirm={send}
                loading={busy}
                variant="primary"
                title="Commande récupérée ?"
                message={`Vous avez bien les ${order.item_count} article${order.item_count > 1 ? 's' : ''} de ${order.store.name}. Le client et le commerce seront prévenus.`}
                confirmLabel="Oui, je l’ai récupérée"
            />

            {/* Remise (cash : rappel de la monnaie + « Montant encaissé ») */}
            <Modal
                open={confirming && order.next_status === 'livree'}
                onClose={() => !busy && setConfirming(false)}
                closeable={!busy}
                size="sm"
                title="Commande remise ?"
                description={`Confirmez la remise à ${order.client.first_name}.`}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setConfirming(false)} disabled={busy}>
                            Retour
                        </Button>
                        <Button icon={CheckCircle2} onClick={send} loading={busy} disabled={order.is_cash && !cashCollected}>
                            Confirmer la remise
                        </Button>
                    </>
                }
            >
                {order.is_cash ? (
                    <div className="space-y-3">
                        <div className="rounded-xl bg-accent-50 px-3 py-3 text-sm text-secondary-900 ring-1 ring-accent-300">
                            <p>
                                Encaissez <span className="font-bold">{formatFCFA(order.total_price)}</span>.
                            </p>
                            {order.cash_given !== null && (
                                <p className="mt-1">
                                    Le client remet {formatFCFA(order.cash_given)} : rendez-lui{' '}
                                    <span className="text-base font-bold">{formatFCFA(order.change_due)}</span>.
                                </p>
                            )}
                        </div>
                        <Checkbox
                            id="cash_collected"
                            checked={cashCollected}
                            onChange={(event) => {
                                setCashCollected(event.target.checked);
                                setError(null);
                            }}
                            label={`Montant encaissé (${formatFCFA(order.total_price)})`}
                            description={order.change_due ? `Monnaie rendue : ${formatFCFA(order.change_due)}` : undefined}
                            error={error}
                        />
                    </div>
                ) : (
                    <p className="text-sm text-gray-700">
                        {order.payment_method_label} : le client vous paie via votre numéro Mobile Money. Le client et le commerce seront prévenus.
                    </p>
                )}
            </Modal>
        </DeliveryLayout>
    );
}
