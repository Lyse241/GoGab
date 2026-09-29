import { cn } from '@/utils/cn';
import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useId, useState } from 'react';

/**
 * Recherche de commerces et de produits : envoie ?q= à la page d'accueil.
 */
export default function SearchBar({ className }) {
    const initial = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('q') ?? '' : '';
    const [query, setQuery] = useState(initial);
    // Le header peut afficher plusieurs barres (mobile / desktop) : ids uniques.
    const id = useId();

    const submit = (event) => {
        event.preventDefault();
        const q = query.trim();
        router.get(route('home'), q ? { q } : {}, { preserveState: true });
    };

    const clear = () => {
        setQuery('');
        if (initial) {
            router.get(route('home'), {}, { preserveState: true });
        }
    };

    return (
        <form role="search" onSubmit={submit} className={cn('relative', className)}>
            <label htmlFor={id} className="sr-only">
                Rechercher un commerce ou un produit
            </label>
            <Search
                className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                aria-hidden="true"
            />
            <input
                id={id}
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Restaurant, pharmacie, plat…"
                enterKeyHint="search"
                className="h-11 w-full rounded-full border-0 bg-gray-100 pl-12 pr-11 text-base text-gray-900 placeholder:text-gray-500 focus:bg-white focus:ring-2 focus:ring-primary sm:text-sm [&::-webkit-search-cancel-button]:hidden"
            />
            {query && (
                <button
                    type="button"
                    onClick={clear}
                    className="absolute right-1.5 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-gray-500 hover:bg-gray-200"
                    aria-label="Effacer la recherche"
                >
                    <X className="h-4 w-4" aria-hidden="true" />
                </button>
            )}
        </form>
    );
}
