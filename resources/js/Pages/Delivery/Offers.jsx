import DeliveryOrderCard from '@/Components/Delivery/DeliveryOrderCard';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import DeliveryLayout from '@/Layouts/DeliveryLayout';
import { Head, router, usePoll } from '@inertiajs/react';
import { Check, Megaphone, UserRound } from 'lucide-react';
import { useState } from 'react';

/**
 * Offres de livraison de la zone du livreur (annonces en recherche de livreur).
 */
export default function Offers({ offers, zone, isAvailable }) {
    const [busyId, setBusyId] = useState(null);

    usePoll(15000, { only: ['offers', 'zone', 'isAvailable', 'badges'] });

    const accept = (order) =>
        router.post(
            route('delivery.orders.accept', order.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setBusyId(order.id),
                onFinish: () => setBusyId(null),
            },
        );

    return (
        <DeliveryLayout title="Offres" subtitle={zone ? `Courses à prendre dans la zone ${zone}` : undefined}>
            <Head title="Offres de livraison" />

            {offers.length === 0 ? (
                <EmptyState
                    icon={Megaphone}
                    title="Aucune offre"
                    description={
                        !zone
                            ? 'Aucun quartier de base n’est rattaché à votre profil : choisissez-le dans votre profil.'
                            : !isAvailable
                              ? 'Vous êtes indisponible : passez disponible pour recevoir les offres de votre zone.'
                              : `Aucune course à prendre dans la zone ${zone} pour le moment. La liste se met à jour automatiquement.`
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
                    {offers.map((order) => (
                        <DeliveryOrderCard key={order.id} order={order}>
                            <Button fullWidth size="lg" icon={Check} loading={busyId === order.id} disabled={busyId !== null} onClick={() => accept(order)}>
                                Accepter la course
                            </Button>
                        </DeliveryOrderCard>
                    ))}
                </div>
            )}
        </DeliveryLayout>
    );
}
