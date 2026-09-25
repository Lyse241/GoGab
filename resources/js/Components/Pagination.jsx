import { Link } from '@inertiajs/react';

/**
 * Pagination simple à partir d'un paginateur Laravel (prev_page_url / next_page_url).
 */
export default function Pagination({ paginator }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    const linkClass =
        'rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50';
    const disabledClass =
        'rounded-md border border-gray-200 px-3 py-1.5 text-sm text-gray-300';

    return (
        <nav className="flex items-center justify-between gap-2" aria-label="Pagination">
            {paginator.prev_page_url ? (
                <Link href={paginator.prev_page_url} preserveScroll className={linkClass}>
                    ← Précédent
                </Link>
            ) : (
                <span className={disabledClass}>← Précédent</span>
            )}
            <span className="text-sm text-gray-600">
                Page {paginator.current_page} / {paginator.last_page}
            </span>
            {paginator.next_page_url ? (
                <Link href={paginator.next_page_url} preserveScroll className={linkClass}>
                    Suivant →
                </Link>
            ) : (
                <span className={disabledClass}>Suivant →</span>
            )}
        </nav>
    );
}
