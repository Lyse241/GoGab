import LazyImage from '@/Components/LazyImage';
import QuantityStepper from '@/Components/QuantityStepper';
import Badge from '@/Components/UI/Badge';
import { cn } from '@/utils/cn';
import { formatFCFA, imageUrl } from '@/utils/format';
import { Plus } from 'lucide-react';

/**
 * Produit d'un menu (page commerce, résultats de recherche) : texte à gauche, photo à droite
 * avec le bouton « + » d'ajout au panier.
 *
 * - canOrder : le commerce est ouvert (état calculé par le serveur) ; sinon l'ajout est désactivé
 * - quantity : quantité déjà dans le panier (affiche le sélecteur − / +)
 * - onAdd, onQuantityChange, onRemove : actions du panier
 */
export default function ProductCard({ product, canOrder, quantity = 0, onAdd, onQuantityChange, onRemove }) {
    const available = product.is_available;
    const orderable = available && canOrder;

    return (
        <article
            className={cn(
                'flex gap-3 rounded-2xl p-3 ring-1 sm:p-4',
                available ? 'bg-white ring-gray-100 shadow-card' : 'bg-gray-50 ring-gray-100',
            )}
        >
            <div className="flex min-w-0 flex-1 flex-col">
                <h3 className={cn('font-semibold leading-snug', available ? 'text-gray-900' : 'text-gray-500')}>
                    {product.name}
                </h3>
                {product.description && (
                    <p className="mt-1 line-clamp-2 text-sm text-gray-500">{product.description}</p>
                )}
                <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                    <p className={cn('whitespace-nowrap font-bold', available ? 'text-primary-700' : 'text-gray-400')}>
                        {formatFCFA(product.price)}
                    </p>
                    {!available && (
                        <Badge color="neutral" size="sm">
                            Épuisé
                        </Badge>
                    )}
                </div>
                {!available && quantity > 0 && (
                    // Déjà dans le panier avant de devenir indisponible.
                    <button
                        type="button"
                        onClick={onRemove}
                        className="mt-2 self-start rounded-full px-3 py-1.5 text-sm font-semibold text-danger-600 ring-1 ring-inset ring-danger-200 hover:bg-danger-50"
                    >
                        Retirer du panier
                    </button>
                )}
                {available && quantity > 0 && canOrder && (
                    <QuantityStepper quantity={quantity} label={product.name} onChange={onQuantityChange} />
                )}
            </div>

            <div className="relative shrink-0 self-start">
                <LazyImage
                    src={imageUrl(product.image)}
                    alt=""
                    className={cn('h-24 w-24 rounded-xl sm:h-28 sm:w-28', !available && 'opacity-50 grayscale')}
                />
                {available && quantity === 0 && (
                    <button
                        type="button"
                        onClick={onAdd}
                        disabled={!orderable}
                        aria-label={canOrder ? `Ajouter ${product.name} au panier` : `${product.name} : commerce fermé`}
                        className="absolute -bottom-2 -right-2 flex h-11 w-11 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg ring-4 ring-white transition hover:bg-primary-700 focus:outline-none focus-visible:ring-primary-300 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:shadow-none"
                    >
                        <Plus className="h-6 w-6" aria-hidden="true" />
                    </button>
                )}
            </div>
        </article>
    );
}
