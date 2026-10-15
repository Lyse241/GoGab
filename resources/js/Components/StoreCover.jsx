import LazyImage from '@/Components/LazyImage';
import { categoryIcon } from '@/utils/categoryIcons';
import { cn } from '@/utils/cn';
import { imageUrl } from '@/utils/format';

const sizes = {
    sm: { icon: 'h-7 w-7', tone: 'text-primary-600/70' },
    md: { icon: 'h-14 w-14', tone: 'text-primary-600/60' },
    lg: { icon: 'h-20 w-20', tone: 'text-white/70' },
};

/**
 * Photo de couverture d'un commerce. Sans photo (ou si elle ne charge pas), un fond dégradé aux
 * couleurs de la charte avec l'icône de sa catégorie remplace le bloc gris.
 *
 * - store : { cover_image, category_icon }
 * - size : sm (liste de recherche) | md (carte d'accueil) | lg (en-tête de la page commerce)
 */
export default function StoreCover({ store, size = 'md', className }) {
    const Icon = categoryIcon(store.category_icon);
    const { icon, tone } = sizes[size];

    return (
        <LazyImage
            src={imageUrl(store.cover_image, size === 'lg' ? undefined : 'sm')}
            alt=""
            className={className}
            fallback={
                <span
                    aria-hidden="true"
                    className={cn(
                        'absolute inset-0 flex items-center justify-center bg-gradient-to-br',
                        size === 'lg' ? 'from-primary-600 to-secondary' : 'from-primary-50 via-primary-100 to-secondary-100',
                    )}
                >
                    <Icon className={cn(icon, tone)} strokeWidth={1.5} />
                </span>
            }
        />
    );
}
