import { cn } from '@/utils/cn';

/**
 * Teintes de badge. Les clés couleur (yellow, sky…) sont celles renvoyées par
 * OrderStatus::color() et AccountStatus::color() côté serveur.
 */
export const badgeColors = {
    // Charte
    primary: 'bg-primary-50 text-primary-800 ring-primary-200',
    secondary: 'bg-secondary-50 text-secondary-800 ring-secondary-200',
    accent: 'bg-accent-100 text-secondary-900 ring-accent-300',
    neutral: 'bg-gray-100 text-gray-700 ring-gray-200',
    // Sémantique
    success: 'bg-success-50 text-success-800 ring-success-200',
    warning: 'bg-warning-50 text-warning-800 ring-warning-200',
    danger: 'bg-danger-50 text-danger-700 ring-danger-200',
    info: 'bg-info-50 text-info-800 ring-info-200',
    // Statuts (clés serveur)
    yellow: 'bg-accent-100 text-accent-900 ring-accent-300',
    sky: 'bg-secondary-50 text-secondary-700 ring-secondary-200',
    red: 'bg-danger-50 text-danger-700 ring-danger-200',
    orange: 'bg-orange-50 text-orange-800 ring-orange-200',
    purple: 'bg-purple-50 text-purple-700 ring-purple-200',
    indigo: 'bg-secondary-100 text-secondary-800 ring-secondary-300',
    blue: 'bg-secondary-700 text-white ring-secondary-700',
    teal: 'bg-teal-50 text-teal-800 ring-teal-200',
    green: 'bg-primary-50 text-primary-800 ring-primary-200',
    gray: 'bg-gray-100 text-gray-700 ring-gray-200',
};

const dotColors = {
    blue: 'bg-accent',
    yellow: 'bg-accent-500',
    accent: 'bg-accent-500',
};

const sizes = {
    sm: 'gap-1 px-2 py-0.5 text-xs',
    md: 'gap-1.5 px-2.5 py-1 text-xs',
    lg: 'gap-1.5 px-3 py-1 text-sm',
};

/**
 * Petite étiquette (catégorie, compteur, statut).
 * - color : clé de `badgeColors`
 * - dot : pastille devant le texte ; icon : composant lucide
 */
export default function Badge({ color = 'neutral', size = 'md', dot = false, icon: Icon, className, children }) {
    return (
        <span
            className={cn(
                'inline-flex items-center whitespace-nowrap rounded-full font-semibold ring-1 ring-inset',
                badgeColors[color] ?? badgeColors.neutral,
                sizes[size],
                className,
            )}
        >
            {dot && (
                <span
                    className={cn('h-1.5 w-1.5 rounded-full', dotColors[color] ?? 'bg-current opacity-70')}
                    aria-hidden="true"
                />
            )}
            {Icon && <Icon className="h-3.5 w-3.5" aria-hidden="true" />}
            {children}
        </span>
    );
}
