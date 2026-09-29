import { cn } from '@/utils/cn';

/**
 * Bloc gris animé affiché pendant un chargement.
 */
export default function Skeleton({ className }) {
    return <div className={cn('animate-pulse rounded-lg bg-gray-200', className)} aria-hidden="true" />;
}

/**
 * Plusieurs lignes de texte (la dernière plus courte).
 */
export function SkeletonText({ lines = 3, className }) {
    return (
        <div className={cn('space-y-2', className)} aria-hidden="true">
            {Array.from({ length: lines }, (_, index) => (
                <Skeleton key={index} className={cn('h-3.5', index === lines - 1 && lines > 1 ? 'w-2/3' : 'w-full')} />
            ))}
        </div>
    );
}

/**
 * Carte commerce en cours de chargement (image + titre + infos).
 */
export function SkeletonCard({ className }) {
    return (
        <div
            className={cn('overflow-hidden rounded-2xl bg-white shadow-card ring-1 ring-gray-100', className)}
            role="status"
            aria-label="Chargement…"
        >
            <Skeleton className="aspect-[16/9] rounded-none" />
            <div className="space-y-3 p-4">
                <Skeleton className="h-4 w-3/4" />
                <SkeletonText lines={2} />
            </div>
        </div>
    );
}
