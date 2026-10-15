import { cn } from '@/utils/cn';
import { Star } from 'lucide-react';

/**
 * Étoiles de 1 à 5.
 * - lecture seule : <StarRating value={4} /> (étoiles pleines jusqu'à la note arrondie)
 * - saisie : <StarRating value={n} onChange={setN} label="Note du commerce" /> (boutons radio accessibles)
 */
export default function StarRating({ value = 0, onChange, label, size = 'md', className }) {
    const dimension = size === 'lg' ? 'h-8 w-8' : size === 'sm' ? 'h-4 w-4' : 'h-5 w-5';
    const rounded = Math.round(value);

    if (!onChange) {
        return (
            <span className={cn('inline-flex items-center gap-0.5', className)} role="img" aria-label={`${value} sur 5`}>
                {[1, 2, 3, 4, 5].map((n) => (
                    <Star
                        key={n}
                        className={cn(dimension, n <= rounded ? 'fill-accent text-accent-600' : 'text-gray-300')}
                        aria-hidden="true"
                    />
                ))}
            </span>
        );
    }

    return (
        <div role="radiogroup" aria-label={label} className={cn('inline-flex items-center gap-1', className)}>
            {[1, 2, 3, 4, 5].map((n) => (
                <button
                    key={n}
                    type="button"
                    role="radio"
                    aria-checked={value === n}
                    aria-label={`${n} sur 5`}
                    onClick={() => onChange(n)}
                    className="tap-area rounded-full p-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                >
                    <Star className={cn(dimension, n <= value ? 'fill-accent text-accent-600' : 'text-gray-300')} aria-hidden="true" />
                </button>
            ))}
        </div>
    );
}
