import DeliveryOrderCard from '@/Components/Delivery/DeliveryOrderCard';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import DeliveryLayout from '@/Layouts/DeliveryLayout';
import { Head, router, usePoll } from '@inertiajs/react';
import { Bike, Megaphone } from 'lucide-react';
import { useState } from 'react';

// Libellé du bouton selon l'étape suivante (proposée par le serveur, OrderWorkflow).
const NEXT_STEP = {
    en_livraison: 'J’ai récupéré la commande',
    arrive: 'Je suis arrivé chez le client',
    livree: 'Commande remise au client',
};

/**
 * Course(s) en cours du livreur, avec l'étape suivante.
 */
export default function Current({ orders }) {
    const [busyId, setBusyId] = useState(null);

    usePoll(15000, { only: ['orders', 'badges'] });

    const advance = (order) =>
        router.put(
            route('orders.status.update', order.id),
            { status: order.next_status },
            {
                preserveScroll: true,
                onStart: () => setBusyId(order.id),
                onFinish: () => setBusyId(null),
            },
        );

    return (
        <DeliveryLayout title="Course en cours" availability={false}>
            <Head title="Course en cours" />

            {orders.length === 0 ? (
                <EmptyState
                    icon={Bike}
                    title="Aucune course en cours"
                    description="Acceptez une offre de votre zone pour commencer une course."
                    action={
                        <Button href={route('delivery.offers')} icon={Megaphone}>
                            Voir les offres
                        </Button>
                    }
                />
            ) : (
                <div className="space-y-3">
                    {orders.map((order) => (
                        <DeliveryOrderCard key={order.id} order={order}>
                            {order.next_status && (
                                <Button
                                    fullWidth
                                    size="lg"
                                    variant="secondary"
                                    loading={busyId === order.id}
                                    disabled={busyId !== null}
                                    onClick={() => advance(order)}
                                >
                                    {NEXT_STEP[order.next_status]}
                                </Button>
                            )}
                        </DeliveryOrderCard>
                    ))}
                </div>
            )}
        </DeliveryLayout>
    );
}
