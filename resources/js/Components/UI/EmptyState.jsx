import { cn } from '@/utils/cn';
import { Inbox } from 'lucide-react';

/**
 * Message quand une liste est vide (aucune commande, panier vide, pas de résultat…).
 * - icon : composant lucide ; action : bouton ou lien
 */
export default function EmptyState({ icon: Icon = Inbox, title, description, action, className }) {
    return (
        <div
            className={cn(
                'flex flex-col items-center rounded-2xl border-2 border-dashed border-gray-200 bg-white px-6 py-12 text-center',
                className,
            )}
        >
            <span className="flex h-16 w-16 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                <Icon className="h-8 w-8" aria-hidden="true" />
            </span>
            <h3 className="mt-4 text-base font-semibold text-secondary-900">{title}</h3>
            {description && <p className="mt-1.5 max-w-sm text-sm text-gray-500">{description}</p>}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}
