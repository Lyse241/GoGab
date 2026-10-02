import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import EmptyState from '@/Components/UI/EmptyState';
import { useToast } from '@/Components/UI/Toast';
import { useNotifications } from '@/Contexts/NotificationsContext';
import DeliveryLayout from '@/Layouts/DeliveryLayout';
import { formatFCFA } from '@/utils/format';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import { ArrowDown, Banknote, Bike, Check, Clock, MapPin, Megaphone, Package, Smartphone, Store, UserRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const REFRESH_INTERVAL = 8000;

function formatAge(seconds) {
    if (seconds < 60) {
        return 'à l’instant';
    }
    const minutes = Math.floor(seconds / 60);

    return minutes < 60 ? `il y a ${minutes} min` : `il y a ${Math.floor(minutes / 60)} h ${String(minutes % 60).padStart(2, '0')}`;
}

/**
 * Secondes écoulées depuis la réception de la page (pour faire vieillir les annonces entre deux
 * rafraîchissements sans dépendre de l'horloge du téléphone).
 */
function useSecondsSince(stamp) {
    const [seconds, setSeconds] = useState(0);

    useEffect(() => {
        const start = Date.now();
        setSeconds(0);
        const timer = setInterval(() => setSeconds(Math.floor((Date.now() - start) / 1000)), 15000);

        return () => clearInterval(timer);
    }, [stamp]);

    return seconds;
}

/**
 * Mode de paiement ; en cash, ce que le client remettra et la monnaie à prévoir, pour savoir
 * avant d'accepter si l'on aura la monnaie.
 */
function PaymentLine({ offer }) {
    if (!offer.is_cash) {
        return (
            <p className="flex items-start gap-2 text-sm text-gray-700">
                <Smartphone className="mt-0.5 h-4 w-4 shrink-0 text-gray-500" aria-hidden="true" />
                <span>
                    {offer.payment_method_label} · {formatFCFA(offer.total_price)}
                </span>
            </p>
        );
    }

    return (
        <div className="flex items-start gap-2 rounded-xl bg-accent-50 px-3 py-2 text-sm text-secondary-900 ring-1 ring-accent-200">
            <Banknote className="mt-0.5 h-4 w-4 shrink-0 text-accent-700" aria-hidden="true" />
            <p>
                <span className="font-semibold">Cash</span> · à encaisser {formatFCFA(offer.total_price)}
                {offer.cash_given !== null && (
                    <>
                        {' '}
                        · le client paie avec <span className="font-semibold">{formatFCFA(offer.cash_given)}</span> · monnaie à prévoir :{' '}
                        <span className="font-bold">{formatFCFA(offer.change_due)}</span>
                    </>
                )}
            </p>
        </div>
    );
}

function OfferCard({ offer, elapsed, disabled, busy, onAccept }) {
    return (
        <Card className="flex flex-col gap-3">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate text-lg font-bold text-secondary-900">{offer.store}</p>
                    <p className="flex items-center gap-1 text-xs text-gray-500">
                        <Clock className="h-3.5 w-3.5" aria-hidden="true" />
                        Annonce {formatAge(offer.announced_seconds + elapsed)} · {offer.number}
                    </p>
                </div>
                <div className="shrink-0 rounded-xl bg-primary-50 px-3 py-1.5 text-right ring-1 ring-primary-200">
                    <p className="text-[11px] font-medium uppercase tracking-wide text-primary-800">Gain</p>
                    <p className="text-lg font-bold leading-tight text-primary-800">{formatFCFA(offer.earning)}</p>
                </div>
            </div>

            {/* Trajet : retrait → livraison */}
            <ol className="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                <li className="flex items-center gap-2">
                    <Store className="h-4 w-4 shrink-0 text-primary-600" aria-hidden="true" />
                    <span className="text-gray-600">Retrait :</span>
                    <span className="font-semibold text-gray-900">{offer.store_neighborhood ?? '—'}</span>
                </li>
                <li className="py-0.5 pl-0.5" aria-hidden="true">
                    <ArrowDown className="h-3.5 w-3.5 text-gray-400" />
                </li>
                <li className="flex items-center gap-2">
                    <MapPin className="h-4 w-4 shrink-0 text-secondary" aria-hidden="true" />
                    <span className="text-gray-600">Livraison :</span>
                    <span className="font-semibold text-gray-900">{offer.neighborhood ?? '—'}</span>
                </li>
            </ol>

            <p className="flex items-center gap-2 text-sm text-gray-700">
                <Package className="h-4 w-4 shrink-0 text-gray-500" aria-hidden="true" />
                {offer.item_count} article{offer.item_count > 1 ? 's' : ''}
            </p>

            <PaymentLine offer={offer} />

            <Button fullWidth size="lg" icon={Check} loading={busy} disabled={disabled} onClick={() => onAccept(offer)}>
                Accepter la course
            </Button>
        </Card>
    );
}

/**
 * Offres de livraison autour du livreur (commerces de la zone de son quartier de base), de la
 * plus ancienne à la plus récente. Rafraîchies toutes les 8 s, toast à l'arrivée d'une offre.
 * Acceptation avec confirmation ; si un autre livreur a été plus rapide, le serveur répond
 * « Cette course vient d'être prise » et la liste rechargée ne contient plus la carte.
 */
export default function Offers({ offers, zone, isAvailable, busy }) {
    const toast = useToast();
    const notifications = useNotifications();
    const [confirming, setConfirming] = useState(null);
    const [accepting, setAccepting] = useState(null);
    const known = useRef(new Set(offers.map((offer) => offer.id)));
    const elapsed = useSecondsSince(offers);

    usePoll(REFRESH_INTERVAL, { only: ['offers', 'isAvailable', 'busy', 'badges', 'courier'] });

    // Nouvelle offre depuis le dernier rafraîchissement : toast (et cloche à jour).
    useEffect(() => {
        const arrived = offers.filter((offer) => !known.current.has(offer.id));
        known.current = new Set(offers.map((offer) => offer.id));

        if (arrived.length === 0) {
            return;
        }

        toast.info(arrived.length > 1 ? `${arrived.length} nouvelles courses dans votre zone.` : `${arrived[0].store} → ${arrived[0].neighborhood} · gain ${formatFCFA(arrived[0].earning)}`, {
            title: arrived.length > 1 ? 'Nouvelles offres !' : 'Nouvelle offre !',
        });
        notifications.refresh?.();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [offers]);

    const accept = () => {
        const offer = confirming;
        router.post(
            route('delivery.orders.accept', offer.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setAccepting(offer.id),
                onFinish: () => {
                    setAccepting(null);
                    setConfirming(null);
                },
            },
        );
    };

    return (
        <DeliveryLayout title="Offres" subtitle={zone ? `Courses autour de vous · zone ${zone}` : undefined}>
            <Head title="Offres de livraison" />

            {busy && (
                <div className="flex flex-wrap items-center gap-3 rounded-2xl bg-secondary-50 px-4 py-3 text-sm text-secondary-900 ring-1 ring-secondary-200" role="status">
                    <Bike className="h-5 w-5 shrink-0 text-secondary" aria-hidden="true" />
                    <p className="min-w-0 flex-1">{busy.message}</p>
                    <Link href={route('delivery.current')} className="inline-flex min-h-tap items-center font-semibold text-secondary underline">
                        Ma course {busy.number}
                    </Link>
                </div>
            )}

            {offers.length === 0 ? (
                <EmptyState
                    icon={Megaphone}
                    title="Aucune offre"
                    description={
                        !zone
                            ? 'Aucun quartier de base n’est rattaché à votre profil : choisissez-le dans votre profil.'
                            : !isAvailable
                              ? 'Vous êtes indisponible : passez disponible pour recevoir les offres de votre zone.'
                              : `Aucune course à prendre dans la zone ${zone} pour le moment. La liste se met à jour toute seule.`
                    }
                    action={
                        !zone && (
                            <Button href={route('delivery.profile')} variant="outline" icon={UserRound}>
                                Mon profil
                            </Button>
                        )
                    }
                />
            ) : (
                <div className="space-y-3">
                    {offers.map((offer) => (
                        <OfferCard
                            key={offer.id}
                            offer={offer}
                            elapsed={elapsed}
                            busy={accepting === offer.id}
                            disabled={Boolean(busy) || accepting !== null}
                            onAccept={setConfirming}
                        />
                    ))}
                </div>
            )}

            <ConfirmDialog
                open={confirming !== null}
                onClose={() => accepting === null && setConfirming(null)}
                onConfirm={accept}
                loading={accepting !== null}
                variant="primary"
                title="Accepter cette course ?"
                message={
                    confirming &&
                    `${confirming.store} (${confirming.store_neighborhood}) → ${confirming.neighborhood} · gain ${formatFCFA(confirming.earning)}.` +
                        (confirming.is_cash && confirming.change_due ? ` Prévoyez ${formatFCFA(confirming.change_due)} de monnaie.` : '')
                }
                confirmLabel="Accepter la course"
            />
        </DeliveryLayout>
    );
}
