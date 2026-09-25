import ConfirmDeleteButton from '@/Components/ConfirmDeleteButton';
import ImageField from '@/Components/ImageField';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatPrice, imageUrl } from '@/utils/format';
import { Head, Link, useForm } from '@inertiajs/react';

function ProductsSection({ store, products }) {
    return (
        <section className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-6">
            <div className="flex items-center justify-between gap-2">
                <h3 className="text-lg font-semibold text-secondary">
                    Produits ({products.length})
                </h3>
                <Link
                    href={route('admin.stores.products.create', store.id)}
                    className="rounded-full bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"
                >
                    + Ajouter un produit
                </Link>
            </div>

            {products.length === 0 ? (
                <p className="mt-4 text-sm text-gray-500">Aucun produit pour le moment.</p>
            ) : (
                <ul className="mt-4 divide-y divide-gray-100">
                    {products.map((product) => (
                        <li key={product.id} className="flex items-center gap-3 py-3">
                            <div className="h-12 w-12 shrink-0 overflow-hidden rounded-md bg-gray-100">
                                {product.image && (
                                    <img
                                        src={imageUrl(product.image)}
                                        alt=""
                                        loading="lazy"
                                        className="h-full w-full object-cover"
                                    />
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="font-medium text-gray-900">{product.name}</p>
                                <p className="text-sm text-gray-500">{formatPrice(product.price)}</p>
                            </div>
                            <div className="flex shrink-0 flex-col items-end gap-1 sm:flex-row sm:items-center sm:gap-4">
                                <Link
                                    href={route('admin.products.edit', product.id)}
                                    className="text-sm font-medium text-secondary hover:underline"
                                >
                                    Modifier
                                </Link>
                                <ConfirmDeleteButton
                                    url={route('admin.products.destroy', product.id)}
                                    title={`Supprimer « ${product.name} » ?`}
                                    message="Impossible si ce produit figure déjà dans des commandes."
                                />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export default function Form({ store, products, categories }) {
    const isEdit = store !== null;

    const { data, setData, post, processing, errors, reset } = useForm({
        name: store?.name ?? '',
        category: store?.category ?? '',
        image: null,
        // Envoi de fichier : PUT simulé via POST + _method (limite HTML/PHP).
        ...(isEdit ? { _method: 'put' } : {}),
    });

    const submit = (e) => {
        e.preventDefault();
        post(isEdit ? route('admin.stores.update', store.id) : route('admin.stores.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => reset('image'),
        });
    };

    const title = isEdit ? `Modifier « ${store.name} »` : 'Nouvelle boutique';

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-secondary">{title}</h2>
            }
        >
            <Head title={title} />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <Link
                    href={route('admin.stores.index')}
                    className="text-sm font-medium text-secondary hover:underline"
                >
                    ← Toutes les boutiques
                </Link>

                <form
                    onSubmit={submit}
                    className="space-y-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-6"
                >
                    <div>
                        <InputLabel htmlFor="name" value="Nom de la boutique" />
                        <TextInput
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="mt-1 block w-full"
                            required
                            isFocused={!isEdit}
                        />
                        <InputError message={errors.name} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="category" value="Catégorie" />
                        <TextInput
                            id="category"
                            list="categories"
                            value={data.category}
                            onChange={(e) => setData('category', e.target.value)}
                            className="mt-1 block w-full"
                            placeholder="Ex : Restaurant, Pharmacie, Épicerie…"
                            required
                        />
                        <datalist id="categories">
                            {categories.map((category) => (
                                <option key={category} value={category} />
                            ))}
                        </datalist>
                        <InputError message={errors.category} className="mt-1" />
                    </div>

                    <ImageField
                        current={store?.image}
                        file={data.image}
                        onChange={(file) => setData('image', file)}
                        error={errors.image}
                    />

                    <div className="flex items-center justify-end gap-4">
                        <PrimaryButton disabled={processing}>
                            {isEdit ? 'Enregistrer' : 'Créer la boutique'}
                        </PrimaryButton>
                    </div>
                </form>

                {isEdit && <ProductsSection store={store} products={products} />}
            </div>
        </AuthenticatedLayout>
    );
}
