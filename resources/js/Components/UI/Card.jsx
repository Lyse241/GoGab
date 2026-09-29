import { cn } from '@/utils/cn';
import { Link } from '@inertiajs/react';

const paddings = {
    none: '',
    sm: 'p-3',
    md: 'p-4 sm:p-5',
    lg: 'p-5 sm:p-7',
};

/**
 * Carte aérée aux coins arrondis. `href` la rend cliquable (lien Inertia) avec effet de survol.
 */
export default function Card({ as: Tag = 'div', href, padding = 'md', className, children, ...props }) {
    const classes = cn(
        'rounded-2xl bg-white shadow-card ring-1 ring-gray-100',
        paddings[padding],
        href &&
            'block transition duration-200 hover:-translate-y-0.5 hover:shadow-card-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary',
        className,
    );

    if (href) {
        return (
            <Link href={href} className={classes} {...props}>
                {children}
            </Link>
        );
    }

    return (
        <Tag className={classes} {...props}>
            {children}
        </Tag>
    );
}

/**
 * En-tête de carte : titre, description et action (bouton, lien) alignée à droite.
 */
export function CardHeader({ title, description, action, className }) {
    return (
        <div className={cn('mb-4 flex items-start justify-between gap-3', className)}>
            <div className="min-w-0">
                <h3 className="text-base font-semibold text-secondary-900">{title}</h3>
                {description && <p className="mt-0.5 text-sm text-gray-500">{description}</p>}
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </div>
    );
}

export function CardFooter({ className, children }) {
    return (
        <div
            className={cn(
                'mt-5 flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end',
                className,
            )}
        >
            {children}
        </div>
    );
}
