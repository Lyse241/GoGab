import CourierCard from '@/Components/Business/CourierCard';
import { SkeletonList } from '@/Components/UI/Skeleton';
import useListLoading from '@/Hooks/useListLoading';
import DeliveryAnnouncement from '@/Components/Business/DeliveryAnnouncement';
import OrderActions from '@/Components/Business/OrderActions';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/UI/Pagination';
import StatusBadge from '@/Components/UI/StatusBadge';
import Switch from '@/Components/UI/Switch';
import Tabs from '@/Components/UI/Tabs';
import { useToast } from '@/Components/UI/Toast';
import { useNotifications } from '@/Contexts/NotificationsContext';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatFCFA } from '@/utils/format';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import { ChevronRight, Clock, MapPin, MessageSquareText, ReceiptText, Wallet } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const REFRESH_INTERVAL = 10000;
const SOUND_KEY = 'gogab_order_sound';

const TABS = [
    { value: 'new', label: 'Nouvelles' },
    { value: 'preparing', label: 'En préparation' },
    { value: 'searching', label: 'Attente livreur' },
    { value: 'delivering', label: 'En livraison' },
    { value: 'finished', label: 'Terminées' },
];

const EMPTY = {
    new: 'Aucune nouvelle commande. Elles apparaissent ici automatiquement.',
    preparing: 'Aucune commande en préparation.',
    searching: 'Aucune commande en attente d’un livreur. Publiez l’annonce quand une commande est prête.',
    delivering: 'Aucune commande en cours de livraison.',
    finished: 'Aucune commande terminée pour le moment.',
};

function readSound() {
    try {
        return window.localStorage.getItem(SOUND_KEY) !== 'off';
    } catch {
        return true;
    }
}

/**
 * Son discret (deux notes courtes) à l'arrivée d'une commande. Le navigateur peut le bloquer tant
 * que l'utilisateur n'a pas interagi avec la page : on ignore alors l'erreur.
 */
function playChime() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        const context = new AudioContext();
        [880, 1175].forEach((frequency, index) => {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            const start = context.currentTime + index * 0.18;
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.12, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.25);
            oscillator.connect(gain).connect(context.destination);
            oscillator.start(start);
            oscillator.stop(start + 0.3);
        });
        setTimeout(() => context.close(), 1000);
    } catch {
        // Audio indisponible : le toast suffit.
    }
}

function OrderCard({ order }) {
    const itemCount = order.items.reduce((sum, item) => sum + item.quantity, 0);

    return (
        <Card className="flex flex-col gap-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <Link href={route('business.orders.show', order.id)} className="group min-w-0">
                    <p className="font-bold text-secondary-900 group-hover:underline">{order.number}</p>
                    <p className="flex items-center gap-1 text-sm text-gray-500">
                        <Clock className="h-3.5 w-3.5" aria-hidden="true" />
                        {order.created_at} · {order.client}
                    </p>
                </Link>
                <div className="flex items-center gap-2">
                    <StatusBadge status={order.status} size="sm" />
                    <p className="text-lg font-bold text-gray-900">{formatFCFA(order.total_price)}</p>
                </div>
            </div>

            <ul className="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                {order.items.map((item) => (
                    <li key={item.id} className="flex justify-between gap-2 py-0.5">
                        <span className="text-gray-800">
                            <span className="font-semibold">{item.quantity} ×</span> {item.name}
                        </span>
                    </li>
                ))}
            </ul>

            <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600">
                <span className="inline-flex items-center gap-1">
                    <MapPin className="h-4 w-4 text-primary-600" aria-hidden="true" />
                    {order.neighborhood}
                </span>
                <span className="inline-flex items-center gap-1">
                    <Wallet className="h-4 w-4 text-primary-600" aria-hidden="true" />
                    {order.payment_method_label}
                    {order.change_due !== null && ` · remet ${formatFCFA(order.cash_given)}`}
                </span>
                <span>
                    {itemCount} article{itemCount > 1 ? 's' : ''}
                </span>
            </div>

            {order.client_note && (
                <p className="flex items-start gap-2 rounded-xl bg-accent-50 px-3 py-2 text-sm text-secondary-900 ring-1 ring-accent-200">
                    <MessageSquareText className="mt-0.5 h-4 w-4 shrink-0 text-accent-700" aria-hidden="true" />
                    {order.client_note}
                </p>
            )}

            <DeliveryAnnouncement order={order} />
            <CourierCard courier={order.courier} className="rounded-xl bg-secondary-50/60 px-3 py-2" />

            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3">
                <OrderActions order={order} size="sm" />
                <Link
                    href={route('business.orders.show', order.id)}
                    className="ml-auto inline-flex min-h-tap items-center gap-1 text-sm font-semibold text-secondary hover:underline"
                >
                    Détail
                    <ChevronRight className="h-4 w-4" aria-hidden="true" />
                </Link>
            </div>
        </Card>
    );
}

/**
 * Commandes reçues, en direct (rafraîchies toutes les 10 s) : onglets par étape, actions
 * (accepter, refuser, préparer, publier l'annonce, relancer / annuler une annonce sans réponse),
 * livreur assigné avec bouton Appeler, toast + son à l'arrivée d'une commande.
 */
export default function Index({ orders, tab, counts, pendingIds }) {
    const listLoading = useListLoading();
    const toast = useToast();
    const notifications = useNotifications();
    const [sound, setSound] = useState(readSound);
    const known = useRef(new Set(pendingIds));

    usePoll(REFRESH_INTERVAL, { only: ['orders', 'counts', 'pendingIds'] });

    // Nouvelle commande arrivée depuis le dernier rafraîchissement : toast, son, cloche.
    useEffect(() => {
        const arrived = pendingIds.filter((id) => !known.current.has(id));
        known.current = new Set(pendingIds);

        if (arrived.length === 0) {
            return;
        }

        toast.info('À accepter ou refuser dans l’onglet « Nouvelles ».', {
            title: arrived.length > 1 ? `${arrived.length} nouvelles commandes !` : 'Nouvelle commande !',
        });
        if (sound) {
            playChime();
        }
        notifications.refresh?.();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pendingIds]);

    const toggleSound = (value) => {
        setSound(value);
        try {
            window.localStorage.setItem(SOUND_KEY, value ? 'on' : 'off');
        } catch {
            // Préférence non mémorisée : valable pour cette page.
        }
        if (value) {
            playChime(); // aperçu (et autorisation audio du navigateur)
        }
    };

    const changeTab = (value) =>
        router.get(route('business.orders.index'), value === 'new' ? {} : { tab: value }, { preserveScroll: true, preserveState: true, only: ['orders', 'tab', 'counts', 'pendingIds'] });

    return (
        <DashboardLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-secondary-900">Commandes</h1>
                        <p className="text-sm text-gray-500">Mises à jour automatiquement toutes les 10 secondes.</p>
                    </div>
                    <Switch size="sm" reverse checked={sound} onChange={toggleSound} label={<span className="text-sm font-medium">Son des nouvelles commandes</span>} />
                </div>
            }
        >
            <Head title="Commandes" />

            <div className="mx-auto max-w-4xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                <Tabs
                    label="Étapes des commandes"
                    value={tab}
                    onChange={changeTab}
                    items={TABS.map((item) => ({ ...item, count: counts[item.value] }))}
                />

                {listLoading ? (
                    <SkeletonList />
                ) : orders.data.length === 0 ? (
                    <EmptyState icon={ReceiptText} title="Rien pour le moment" description={EMPTY[tab]} />
                ) : (
                    <div className="space-y-3">
                        {orders.data.map((order) => (
                            <OrderCard key={order.id} order={order} />
                        ))}
                    </div>
                )}

                <Pagination paginator={orders} />
            </div>
        </DashboardLayout>
    );
}
