import Modal from '@/Components/UI/Modal';
import { useNeighborhood } from '@/Contexts/NeighborhoodContext';
import { cn } from '@/utils/cn';
import { usePage } from '@inertiajs/react';
import { Check, ChevronDown, MapPin } from 'lucide-react';
import { useMemo, useState } from 'react';

const ZONE_ORDER = ['Nord', 'Centre', 'Est', 'Sud'];

/**
 * Bouton « Livrer à … » du header : choix du quartier, regroupé par zone de Libreville.
 */
export default function NeighborhoodSelector({ className }) {
    const { neighborhoods = [] } = usePage().props;
    const { neighborhoodId, setNeighborhoodId } = useNeighborhood();
    const [open, setOpen] = useState(false);

    const selected = neighborhoods.find((neighborhood) => neighborhood.id === neighborhoodId);

    const zones = useMemo(() => {
        const groups = {};
        neighborhoods.forEach((neighborhood) => {
            const zone = neighborhood.zone ?? 'Autres';
            (groups[zone] ??= []).push(neighborhood);
        });

        return Object.entries(groups).sort(
            ([a], [b]) => (ZONE_ORDER.indexOf(a) + 1 || 99) - (ZONE_ORDER.indexOf(b) + 1 || 99),
        );
    }, [neighborhoods]);

    const choose = (id) => {
        setNeighborhoodId(id);
        setOpen(false);
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className={cn(
                    'inline-flex min-h-tap max-w-[11rem] items-center gap-1.5 rounded-full px-2 text-left text-sm hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:max-w-[14rem] sm:px-3',
                    className,
                )}
                aria-haspopup="dialog"
            >
                <MapPin className="h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" />
                <span className="min-w-0 leading-tight">
                    <span className="block text-[11px] font-medium uppercase tracking-wide text-gray-500">
                        Livrer à
                    </span>
                    <span className="block truncate font-semibold text-secondary-900">
                        {selected?.name ?? 'Choisir un quartier'}
                    </span>
                </span>
                <ChevronDown className="h-4 w-4 shrink-0 text-gray-500" aria-hidden="true" />
            </button>

            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title="Votre quartier"
                description="Les commerces et livreurs proches sont ceux de votre zone."
                size="lg"
            >
                <div className="space-y-5">
                    {zones.map(([zone, items]) => (
                        <section key={zone}>
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500">Zone {zone}</h3>
                            <ul className="mt-2 grid grid-cols-2 gap-2">
                                {items.map((neighborhood) => {
                                    const active = neighborhood.id === neighborhoodId;

                                    return (
                                        <li key={neighborhood.id}>
                                            <button
                                                type="button"
                                                onClick={() => choose(neighborhood.id)}
                                                aria-pressed={active}
                                                className={cn(
                                                    'flex min-h-tap w-full items-center justify-between gap-2 rounded-xl px-3 text-left text-sm font-medium ring-1 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                                                    active
                                                        ? 'bg-primary-50 text-primary-800 ring-primary-300'
                                                        : 'bg-white text-gray-800 ring-gray-200 hover:bg-gray-50',
                                                )}
                                            >
                                                <span className="truncate">{neighborhood.name}</span>
                                                {active && <Check className="h-4 w-4 shrink-0" aria-hidden="true" />}
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </section>
                    ))}
                </div>
            </Modal>
        </>
    );
}
