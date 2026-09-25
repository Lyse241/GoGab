import { MAX_QUANTITY } from '@/Contexts/CartContext';

const buttonClass =
    'flex h-8 w-8 items-center justify-center rounded-full text-lg font-bold text-primary-700 hover:bg-primary-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:opacity-40';

/**
 * Contrôle − / + de la quantité d'un article du panier (− à 1 retire l'article).
 */
export default function QuantityStepper({ quantity, onChange, label }) {
    return (
        <div className="flex items-center gap-1 rounded-full bg-primary-50 p-0.5">
            <button
                type="button"
                className={buttonClass}
                onClick={() => onChange(quantity - 1)}
                aria-label={`Retirer un ${label}`}
            >
                −
            </button>
            <span className="w-6 text-center text-sm font-semibold" aria-live="polite">
                {quantity}
            </span>
            <button
                type="button"
                className={buttonClass}
                onClick={() => onChange(quantity + 1)}
                disabled={quantity >= MAX_QUANTITY}
                aria-label={`Ajouter un ${label}`}
            >
                +
            </button>
        </div>
    );
}
