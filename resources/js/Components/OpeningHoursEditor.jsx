import Checkbox from '@/Components/UI/Checkbox';
import { FieldError } from '@/Components/UI/Field';
import { cn } from '@/utils/cn';
import { Copy, MoonStar, Sun } from 'lucide-react';

export const DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

/**
 * Semaine par défaut (lundi → dimanche), même format que StoreHours::schedule() côté serveur.
 */
export function defaultOpeningHours(opensAt = '08:00', closesAt = '22:00') {
    return DAYS.map((_, index) => ({
        day_of_week: index + 1,
        is_closed: false,
        opens_at: opensAt,
        closes_at: closesAt,
    }));
}

/**
 * Validation côté navigateur (le serveur revalide) : un jour ouvert a ses deux heures.
 * Clés d'erreur identiques à celles de Laravel : "opening_hours.0.opens_at".
 */
export function validateOpeningHours(days, name = 'opening_hours') {
    const errors = {};

    days.forEach((day, index) => {
        if (day.is_closed) {
            return;
        }
        if (!day.opens_at) {
            errors[`${name}.${index}.opens_at`] = `${DAYS[index]} : indiquez l’heure d’ouverture, ou cochez « Fermé ce jour ».`;
        }
        if (!day.closes_at) {
            errors[`${name}.${index}.closes_at`] = `${DAYS[index]} : indiquez l’heure de fermeture, ou cochez « Fermé ce jour ».`;
        }
    });

    return errors;
}

/**
 * Remarque affichée sous un créneau : fermeture le lendemain, ou ouverture 24 h/24.
 */
function slotHint(day) {
    if (day.is_closed || !day.opens_at || !day.closes_at) {
        return null;
    }
    if (day.opens_at === day.closes_at) {
        return { icon: Sun, text: 'Ouvert 24 h/24' };
    }
    if (day.closes_at < day.opens_at) {
        return { icon: MoonStar, text: `Ferme le lendemain à ${day.closes_at.replace(':', 'h')}` };
    }

    return null;
}

const timeClasses = (error) =>
    cn(
        'block h-11 w-full rounded-xl border bg-white px-3 text-base text-gray-900 shadow-sm focus:outline-none focus:ring-2 sm:text-sm',
        'disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400',
        error
            ? 'border-danger-400 focus:border-danger-500 focus:ring-danger-200'
            : 'border-gray-300 focus:border-primary focus:ring-primary-100',
    );

/**
 * Horaires de la semaine (7 lignes, lundi → dimanche), utilisables au doigt sur mobile.
 *
 * - value : [{ day_of_week, is_closed, opens_at "HH:MM", closes_at "HH:MM" }] × 7
 * - onChange(nouvelleSemaine)
 * - errors : erreurs du formulaire (clés "opening_hours.N.champ")
 * Une fermeture avant l'ouverture passe minuit (18:00 → 02:00) ; deux heures égales = 24 h/24.
 */
export default function OpeningHoursEditor({ value, onChange, errors = {}, name = 'opening_hours', className }) {
    const update = (index, changes) => onChange(value.map((day, i) => (i === index ? { ...day, ...changes } : day)));

    const copyToAll = (index) => {
        const source = value[index];
        onChange(
            value.map((day) => ({
                ...day,
                is_closed: source.is_closed,
                opens_at: source.opens_at,
                closes_at: source.closes_at,
            })),
        );
    };

    return (
        <fieldset className={className}>
            <legend className="text-sm font-medium text-gray-800">Horaires d’ouverture</legend>
            <p className="mt-1 text-sm text-gray-500">
                Heure de Libreville. Une fermeture après minuit (ex. 18:00 → 02:00) est acceptée.
            </p>

            <ul className="mt-3 divide-y divide-gray-100 rounded-2xl border border-gray-200 bg-white">
                {value.map((day, index) => {
                    const dayName = DAYS[index];
                    const openError = errors[`${name}.${index}.opens_at`];
                    const closeError = errors[`${name}.${index}.closes_at`];
                    const hint = slotHint(day);
                    const id = `${name}-${index}`;

                    return (
                        <li key={day.day_of_week} className="p-3 sm:px-4">
                            <div className="grid gap-x-4 gap-y-2 sm:grid-cols-[7rem_1fr_auto] sm:items-center">
                                <div className="flex items-center justify-between gap-2 sm:block">
                                    <span className={cn('font-semibold', day.is_closed ? 'text-gray-500' : 'text-secondary-900')}>
                                        {dayName}
                                    </span>
                                    {/* Mobile : case à droite du jour */}
                                    <Checkbox
                                        id={`${id}-closed-mobile`}
                                        label="Fermé ce jour"
                                        checked={day.is_closed}
                                        onChange={(event) => update(index, { is_closed: event.target.checked })}
                                        className="sm:hidden"
                                    />
                                </div>

                                {day.is_closed ? (
                                    <p className="text-sm text-gray-500">Jour de repos</p>
                                ) : (
                                    <div className="grid grid-cols-2 gap-2">
                                        <label className="block">
                                            <span className="sr-only">{dayName}, </span>
                                            <span className="mb-1 block text-xs font-medium text-gray-500">Ouverture</span>
                                            <input
                                                id={`${id}-opens_at`}
                                                type="time"
                                                value={day.opens_at ?? ''}
                                                onChange={(event) => update(index, { opens_at: event.target.value })}
                                                aria-invalid={openError ? true : undefined}
                                                className={timeClasses(openError)}
                                            />
                                        </label>
                                        <label className="block">
                                            <span className="sr-only">{dayName}, </span>
                                            <span className="mb-1 block text-xs font-medium text-gray-500">Fermeture</span>
                                            <input
                                                id={`${id}-closes_at`}
                                                type="time"
                                                value={day.closes_at ?? ''}
                                                onChange={(event) => update(index, { closes_at: event.target.value })}
                                                aria-invalid={closeError ? true : undefined}
                                                className={timeClasses(closeError)}
                                            />
                                        </label>
                                    </div>
                                )}

                                <div className="flex items-center justify-between gap-3 sm:justify-end">
                                    <Checkbox
                                        id={`${id}-closed`}
                                        label="Fermé ce jour"
                                        checked={day.is_closed}
                                        onChange={(event) => update(index, { is_closed: event.target.checked })}
                                        className="hidden sm:block"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => copyToAll(index)}
                                        className="inline-flex min-h-tap items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-secondary hover:bg-secondary-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                        aria-label={`Copier les horaires du ${dayName.toLowerCase()} sur tous les jours`}
                                    >
                                        <Copy className="h-4 w-4" aria-hidden="true" />
                                        <span className="sm:sr-only lg:not-sr-only">Copier sur tous les jours</span>
                                    </button>
                                </div>
                            </div>

                            {hint && (
                                <p className="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-secondary-600 sm:pl-[8rem]">
                                    <hint.icon className="h-3.5 w-3.5" aria-hidden="true" />
                                    {hint.text}
                                </p>
                            )}
                            <FieldError message={openError} className="sm:pl-[8rem]" />
                            {closeError !== openError && <FieldError message={closeError} className="sm:pl-[8rem]" />}
                        </li>
                    );
                })}
            </ul>
        </fieldset>
    );
}
