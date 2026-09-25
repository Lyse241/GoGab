import PublicLayout from '@/Layouts/PublicLayout';
import { formatPrice, imageUrl } from '@/utils/format';
import { Head, Link } from '@inertiajs/react';

function ProductCard({ product }) {
    return (
        <article className="flex gap-4 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-200">
            <div className="h-24 w-24 shrink-0 overflow-hidden rounded-lg bg-gray-200">
                {product.image && (
                    <img
                        src={imageUrl(product.image)}
                        alt={product.name}
                        loading="lazy"
                        className="h-full w-full object-cover"
                    />
                )}
            </div>
            <div className="flex min-w-0 flex-1 flex-col">
                <h3 className="font-semibold text-gray-900">{product.name}</h3>
                {product.description && (
                    <p className="mt-1 line-clamp-2 text-sm text-gray-500">
                        {product.description}
                    </p>
                )}
                <p className="mt-auto pt-2 font-bold text-emerald-700">
                    {formatPrice(product.price)}
                </p>
            </div>
        </article>
    );
}

export default function Show({ store, products }) {
    return (
        <PublicLayout>
            <Head title={store.name} />

            <Link
                href={route('home')}
                className="mt-4 inline-block text-sm font-medium text-emerald-700 hover:underline"
            >
                ← Toutes les boutiques
            </Link>

            <section className="relative mt-3 overflow-hidden rounded-xl bg-gray-800">
                {store.image && (
                    <img
                        src={imageUrl(store.image)}
                        alt=""
                        className="h-40 w-full object-cover opacity-60 sm:h-56"
                    />
                )}
                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-4 sm:p-6">
                    <span className="rounded bg-emerald-600 px-2 py-0.5 text-xs font-medium text-white">
                        {store.category}
                    </span>
                    <h1 className="mt-2 text-2xl font-bold text-white sm:text-3xl">
                        {store.name}
                    </h1>
                </div>
            </section>

            <h2 className="mb-3 mt-6 text-lg font-semibold text-gray-800">
                Menu ({products.length})
            </h2>

            {products.length === 0 ? (
                <p className="text-gray-500">
                    Cette boutique n'a pas encore de produits.
                </p>
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {products.map((product) => (
                        <ProductCard key={product.id} product={product} />
                    ))}
                </div>
            )}
        </PublicLayout>
    );
}
