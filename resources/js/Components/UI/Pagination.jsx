import { cn } from '@/utils/cn';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

const pageClasses =
    'inline-flex h-10 min-w-10 items-center justify-center rounded-full px-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary';

function Arrow({ href, label, icon: Icon, iconFirst }) {
    const content = (
        <>
            {iconFirst && <Icon className="h-4 w-4" aria-hidden="true" />}
            <span>{label}</span>
            {!iconFirst && <Icon className="h-4 w-4" aria-hidden="true" />}
        </>
    );

    if (!href) {
        return (
            <span className={cn(pageClasses, 'gap-1 text-gray-300')} aria-disabled="true">
                {content}
            </span>
        );
    }

    return (
        <Link href={href} preserveScroll className={cn(pageClasses, 'gap-1 text-secondary hover:bg-secondary-50')}>
            {content}
        </Link>
    );
}

/**
 * Pagination d'un paginateur Laravel (`->paginate()`).
 * Mobile : Précédent / « 2 sur 5 » / Suivant. Desktop : numéros de page.
 */
export default function Pagination({ paginator, className }) {
    if (!paginator || paginator.last_page <= 1) {
        return null;
    }

    // Les liens Laravel contiennent aussi « Précédent » / « Suivant » : on ne garde que les pages.
    const pages = (paginator.links ?? []).slice(1, -1);

    return (
        <nav className={cn('flex items-center justify-between gap-2', className)} aria-label="Pagination">
            <Arrow href={paginator.prev_page_url} label="Précédent" icon={ChevronLeft} iconFirst />

            <span className="text-sm text-gray-600 sm:hidden">
                Page {paginator.current_page} sur {paginator.last_page}
            </span>

            <ul className="hidden items-center gap-1 sm:flex">
                {pages.map((link, index) =>
                    link.url === null ? (
                        <li key={`gap-${index}`} className="px-1 text-gray-400" aria-hidden="true">
                            …
                        </li>
                    ) : (
                        <li key={link.label}>
                            <Link
                                href={link.url}
                                preserveScroll
                                aria-current={link.active ? 'page' : undefined}
                                aria-label={`Page ${link.label}`}
                                className={cn(
                                    pageClasses,
                                    link.active ? 'bg-secondary text-white' : 'text-gray-700 hover:bg-gray-100',
                                )}
                            >
                                {link.label}
                            </Link>
                        </li>
                    ),
                )}
            </ul>

            <Arrow href={paginator.next_page_url} label="Suivant" icon={ChevronRight} />
        </nav>
    );
}
