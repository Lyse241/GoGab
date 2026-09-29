import { cn } from '@/utils/cn';
import { Clock } from 'lucide-react';

/**
 * État d'ouverture d'un commerce : pastille verte « Ouvert » ou grise « Fermé » + détail
 * (« ferme à 22h00 », « ouvre demain à 08h00 », « fermeture temporaire »…).
 *
 * Les valeurs viennent du serveur (is_open_now, status_detail), calculées à l'heure de Libreville :
 *   <OpeningStatusBadge isOpen={store.is_open_now} detail={store.status_detail} />
 *
 * - variant : "default" (sur fond clair) ou "overlay" (sur une photo)
 * - showDetail=false : pastille seule (cartes compactes)
 */
export default function OpeningStatusBadge({ isOpen, detail, variant = 'default', showDetail = true, className }) {
    const overlay = variant === 'overlay';

    return (
        <span className={cn('inline-flex min-w-0 items-center gap-2 text-sm', className)}>
            <span
                className={cn(
                    'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                    isOpen
                        ? 'bg-primary-50 text-primary-800 ring-1 ring-inset ring-primary-200'
                        : 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-200',
                    overlay && 'shadow-sm ring-0',
                    overlay && (isOpen ? 'bg-white text-primary-800' : 'bg-white/90 text-gray-800'),
                )}
            >
                <span
                    className={cn('h-2 w-2 rounded-full', isOpen ? 'bg-primary-500' : 'bg-gray-400')}
                    aria-hidden="true"
                />
                {isOpen ? 'Ouvert' : 'Fermé'}
            </span>
            {showDetail && detail && (
                <span
                    className={cn(
                        'inline-flex min-w-0 items-center gap-1 truncate',
                        overlay ? 'text-white' : 'text-gray-600',
                    )}
                >
                    <Clock className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                    <span className="truncate">{detail}</span>
                </span>
            )}
        </span>
    );
}
