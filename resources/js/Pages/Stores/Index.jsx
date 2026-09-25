import LazyImage from '@/Components/LazyImage';
import PublicLayout from '@/Layouts/PublicLayout';
import { imageUrl } from '@/utils/format';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

function CategoryChip({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={`shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition ${
                active
                    ? 'border-primary-600 bg-primary-600 text-white'
                    : 'border-gray-300 bg-white text-gray-700 hover:border-primary hover:text-primary-700'
            }`}
        >
            {children}
        </button>
    );
}

function StoreCard({ store }) {
    return (
        <Link
            href={route('stores.show', store.id)}
            className="group block overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 transition hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
        >
            <LazyImage
                src={imageUrl(store.image)}
                alt={store.name}
                className="aspect-[3/2]"
            />
            <div className="p-4">
                <h3 className="font-semibold text-gray-900">{store.name}</h3>
                <p className="mt-1 flex items-center justify-between text-sm text-gray-500">
                    <span className="rounded-full bg-accent-100 px-2 py-0.5 text-xs font-semibold text-accent-900">
                        {store.category}
                    </span>
                    <span>
                        {store.products_count} produit
                        {store.products_count > 1 ? 's' : ''}
                    </span>
                </p>
            </div>
        </Link>
    );
}

export default function Index({ stores, categories }) {
    const [selected, setSelected] = useState(null);

    const visibleCategories = selected ? [selected] : categories;

    return (
        <PublicLayout>
            <Head title="Boutiques" />

            <section className="pt-6">
                <h1 className="text-2xl font-bold text-secondary sm:text-3xl">
                    Faites-vous livrer à Libreville
                </h1>
                <p className="mt-1 text-gray-600">
                    Restaurants, pharmacies et épiceries de votre quartier.
                </p>
            </section>

            {/* Filtres : défilement horizontal sur mobile */}
            <div className="no-scrollbar -mx-4 mt-5 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:px-0">
                <CategoryChip active={selected === null} onClick={() => setSelected(null)}>
                    Tout
                </CategoryChip>
                {categories.map((category) => (
                    <CategoryChip
                        key={category}
                        active={selected === category}
                        onClick={() => setSelected(category)}
                    >
                        {category}
                    </CategoryChip>
                ))}
            </div>

            {stores.length === 0 && (
                <p className="mt-10 text-center text-gray-500">
                    Aucune boutique disponible pour le moment.
                </p>
            )}

            {visibleCategories.map((category) => (
                <section key={category} className="mt-6">
                    <h2 className="mb-3 text-lg font-semibold text-secondary">
                        {category}
                    </h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {stores
                            .filter((store) => store.category === category)
                            .map((store) => (
                                <StoreCard key={store.id} store={store} />
                            ))}
                    </div>
                </section>
            ))}
        </PublicLayout>
    );
}
