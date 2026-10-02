import Switch from '@/Components/UI/Switch';
import { cn } from '@/utils/cn';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Grand interrupteur « Je suis disponible / indisponible » (delivery_profiles.is_available).
 * État lu dans la prop partagée `courier` ; affichage optimiste pendant l'envoi.
 * Indisponible : aucune offre ni notification d'annonce.
 */
export default function AvailabilitySwitch({ className }) {
    const { courier } = usePage().props;
    const [pending, setPending] = useState(null);

    if (!courier) {
        return null;
    }

    const available = pending ?? courier.is_available;

    const toggle = (value) =>
        router.patch(
            route('delivery.availability'),
            { is_available: value },
            {
                preserveScroll: true,
                onStart: () => setPending(value),
                onFinish: () => setPending(null),
            },
        );

    return (
        <div
            className={cn(
                'rounded-2xl px-4 py-3 ring-1 transition-colors',
                available ? 'bg-primary-50 ring-primary-200' : 'bg-gray-100 ring-gray-200',
                className,
            )}
        >
            <Switch
                size="lg"
                checked={available}
                onChange={toggle}
                loading={pending !== null}
                label={
                    <span className="flex items-center gap-2 text-base">
                        <span
                            className={cn('h-2.5 w-2.5 rounded-full', available ? 'bg-primary-500' : 'bg-gray-400')}
                            aria-hidden="true"
                        />
                        {available ? 'Je suis disponible' : 'Je suis indisponible'}
                    </span>
                }
                description={
                    available
                        ? `Vous recevez les offres de la zone ${courier.zone ?? '—'}.`
                        : 'Vous ne recevez aucune offre ni notification d’annonce.'
                }
            />
        </div>
    );
}
