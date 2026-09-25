import ImageField from '@/Components/ImageField';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Form({ store, product }) {
    const isEdit = product !== null;

    const { data, setData, post, processing, errors } = useForm({
        name: product?.name ?? '',
        description: product?.description ?? '',
        // Prix stocké "4500.00" : affiché sans décimales inutiles.
        price: product ? String(Number(product.price)) : '',
        image: null,
        ...(isEdit ? { _method: 'put' } : {}),
    });

    const submit = (e) => {
        e.preventDefault();
        post(
            isEdit
                ? route('admin.products.update', product.id)
                : route('admin.stores.products.store', store.id),
            { forceFormData: true },
        );
    };

    const title = isEdit ? `Modifier « ${product.name} »` : 'Nouveau produit';

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-secondary">
                    {title}
                    <span className="block text-sm font-normal text-gray-500">{store.name}</span>
                </h2>
            }
        >
            <Head title={title} />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <Link
                    href={route('admin.stores.edit', store.id)}
                    className="text-sm font-medium text-secondary hover:underline"
                >
                    ← Retour à {store.name}
                </Link>

                <form
                    onSubmit={submit}
                    className="space-y-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-6"
                >
                    <div>
                        <InputLabel htmlFor="name" value="Nom du produit" />
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
                        <InputLabel htmlFor="description" value="Description (facultatif)" />
                        <textarea
                            id="description"
                            rows={3}
                            maxLength={1000}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                        />
                        <InputError message={errors.description} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="price" value="Prix (FCFA)" />
                        <TextInput
                            id="price"
                            type="number"
                            inputMode="numeric"
                            min="1"
                            step="1"
                            value={data.price}
                            onChange={(e) => setData('price', e.target.value)}
                            className="mt-1 block w-full sm:w-48"
                            required
                        />
                        <InputError message={errors.price} className="mt-1" />
                        {isEdit && (
                            <p className="mt-1 text-xs text-gray-500">
                                Les commandes déjà passées conservent leur prix d'origine.
                            </p>
                        )}
                    </div>

                    <ImageField
                        current={product?.image}
                        file={data.image}
                        onChange={(file) => setData('image', file)}
                        error={errors.image}
                    />

                    <div className="flex items-center justify-end">
                        <PrimaryButton disabled={processing}>
                            {isEdit ? 'Enregistrer' : 'Ajouter le produit'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
