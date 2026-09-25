import ConfirmDeleteButton from '@/Components/ConfirmDeleteButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { imageUrl } from '@/utils/format';
import { Head, Link } from '@inertiajs/react';

export default function Index({ stores }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-secondary">
                        Boutiques
                    </h2>
                    <Link
                        href={route('admin.stores.create')}
                        className="rounded-full bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"
                    >
                        + Nouvelle boutique
                    </Link>
                </div>
            }
        >
            <Head title="Boutiques" />

            <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                {stores.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">
                        Aucune boutique pour le moment.
                    </p>
                ) : (
                    <ul className="divide-y divide-gray-100 rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                        {stores.map((store) => (
                            <li key={store.id} className="flex items-center gap-4 p-4">
                                <div className="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                                    {store.image && (
                                        <img
                                            src={imageUrl(store.image)}
                                            alt=""
                                            loading="lazy"
                                            className="h-full w-full object-cover"
                                        />
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold text-gray-900">{store.name}</p>
                                    <p className="text-sm text-gray-500">
                                        {store.category} · {store.products_count} produit
                                        {store.products_count > 1 ? 's' : ''}
                                    </p>
                                </div>
                                <div className="flex shrink-0 flex-col items-end gap-1 sm:flex-row sm:items-center sm:gap-4">
                                    <Link
                                        href={route('admin.stores.edit', store.id)}
                                        className="text-sm font-medium text-secondary hover:underline"
                                    >
                                        Modifier
                                    </Link>
                                    <ConfirmDeleteButton
                                        url={route('admin.stores.destroy', store.id)}
                                        title={`Supprimer « ${store.name} » ?`}
                                        message="La boutique et tous ses produits seront supprimés. Impossible si certains produits ont déjà été commandés."
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
