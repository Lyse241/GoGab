import { formatFCFA, imageUrl } from '@/utils/format';
import { ChevronRight, Store as StoreIcon } from 'lucide-react';

export function StoreThumb({ store, className = 'h-12 w-12' }) {
    return (
        <span className={`flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-primary-50 ${className}`}>
            {store.logo ? (
                <img src={imageUrl(store.logo)} alt="" className="h-full w-full object-cover" />
            ) : (
                <StoreIcon className="h-6 w-6 text-primary-600" aria-hidden="true" />
            )}
        </span>
    );
}

/**
 * Paniers en cours, un bloc par commerce : logo, nom, nombre d'articles, sous-total.
 */
export default function CartList({ carts, onOpen }) {
    return (
        <ul className="space-y-3">
            {carts.map((cart) => (
                <li key={cart.store.id}>
                    <button
                        type="button"
                        onClick={() => onOpen(cart.store.id)}
                        className="flex w-full items-center gap-3 rounded-2xl bg-white p-3 text-left ring-1 ring-gray-200 transition hover:bg-gray-50 hover:ring-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    >
                        <StoreThumb store={cart.store} />
                        <span className="min-w-0 flex-1">
                            <span className="block truncate font-semibold text-secondary-900">{cart.store.name}</span>
                            <span className="block text-sm text-gray-500">
                                {cart.count} article{cart.count > 1 ? 's' : ''} · {formatFCFA(cart.subtotal)}
                            </span>
                        </span>
                        <ChevronRight className="h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" />
                    </button>
                </li>
            ))}
        </ul>
    );
}
