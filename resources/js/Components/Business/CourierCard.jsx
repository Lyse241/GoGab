import { cn } from '@/utils/cn';
import { Bike, Phone } from 'lucide-react';

/**
 * Livreur assigné à une commande : nom, véhicule, téléphone et bouton Appeler.
 */
export default function CourierCard({ courier, className }) {
    if (!courier) {
        return null;
    }

    const vehicle = [courier.vehicle, courier.vehicle_brand].filter(Boolean).join(' · ');

    return (
        <div className={cn('flex items-center gap-3', className)}>
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-secondary-50 text-secondary">
                <Bike className="h-5 w-5" aria-hidden="true" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-xs font-medium uppercase tracking-wide text-gray-500">Livreur</p>
                <p className="truncate font-semibold text-secondary-900">{courier.name}</p>
                {(vehicle || courier.phone) && <p className="truncate text-sm text-gray-600">{[vehicle, courier.phone].filter(Boolean).join(' · ')}</p>}
            </div>
            {courier.phone && (
                <a
                    href={`tel:${courier.phone.replace(/\s/g, '')}`}
                    className="inline-flex min-h-tap shrink-0 items-center gap-2 rounded-full bg-primary-600 px-4 text-sm font-semibold text-white hover:bg-primary-700"
                    aria-label={`Appeler ${courier.name}`}
                >
                    <Phone className="h-4 w-4" aria-hidden="true" />
                    Appeler
                </a>
            )}
        </div>
    );
}
