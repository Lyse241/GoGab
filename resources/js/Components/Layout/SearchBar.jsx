import { cn } from '@/utils/cn';
import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useId, useState } from 'react';

const sizes = {
    md: {
        input: 'h-11 bg-gray-100 pl-12 pr-11 text-base focus:bg-white sm:text-sm',
        icon: 'left-4 h-5 w-5',
    },
    lg: {
        input: 'h-14 bg-white pl-14 pr-12 text-base shadow-lg sm:h-16 sm:text-lg',
        icon: 'left-5 h-6 w-6',
    },
};

/**
 * Recherche de commerces et de produits : envoie ?q= à la page d'accueil
 * (en gardant la catégorie choisie, s'il y en a une).
 *
 * - size : md (header) | lg (bandeau de l'accueil)
 * - placeholder : texte d'invite
 */
export default function SearchBar({ className, size = 'md', placeholder = 'Restaurant, pharmacie, plat…' }) {
    const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
    const initial = params.get('q') ?? '';
    const category = params.get('category');
    const [query, setQuery] = useState(initial);
    // Le header peut afficher plusieurs barres (mobile / desktop) : ids uniques.
    const id = useId();
    const s = sizes[size];

    const go = (q) =>
        router.get(route('home'), Object.fromEntries(Object.entries({ q, category }).filter(([, value]) => value)), {
            preserveState: true,
        });

    const submit = (event) => {
        event.preventDefault();
        go(query.trim());
    };

    const clear = () => {
        setQuery('');
        if (initial) {
            go('');
        }
    };

    return (
        <form role="search" onSubmit={submit} className={cn('relative', className)}>
            <label htmlFor={id} className="sr-only">
                Rechercher un commerce ou un produit
            </label>
            <Search
                className={cn('pointer-events-none absolute top-1/2 -translate-y-1/2 text-gray-400', s.icon)}
                aria-hidden="true"
            />
            <input
                id={id}
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={placeholder}
                enterKeyHint="search"
                className={cn(
                    'w-full rounded-full border-0 text-gray-900 placeholder:text-gray-500 focus:ring-2 focus:ring-primary [&::-webkit-search-cancel-button]:hidden',
                    s.input,
                )}
            />
            {query && (
                <button
                    type="button"
                    onClick={clear}
                    className="absolute right-2 top-1/2 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full text-gray-500 hover:bg-gray-200"
                    aria-label="Effacer la recherche"
                >
                    <X className="h-4 w-4" aria-hidden="true" />
                </button>
            )}
        </form>
    );
}
