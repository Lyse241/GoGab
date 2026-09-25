import InputError from '@/Components/InputError';
import { useCart } from '@/Contexts/CartContext';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatPrice } from '@/utils/format';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const LANDMARKS_MAX = 500;

/**
 * Validation côté client (le serveur revalidera à l'enregistrement).
 * Retourne un objet { champ: message } vide si tout est correct.
 */
function validate(data) {
    const errors = {};

    if (!data.neighborhood_id) {
        errors.neighborhood_id = 'Choisissez votre quartier de livraison.';
    }

    const landmarks = data.address_landmarks.trim();
    if (!landmarks) {
        errors.address_landmarks =
            'Indiquez des repères pour que le livreur trouve votre adresse.';
    } else if (landmarks.length < 10) {
        errors.address_landmarks =
            'Soyez un peu plus précis (10 caractères minimum).';
    } else if (landmarks.length > LANDMARKS_MAX) {
        errors.address_landmarks = `${LANDMARKS_MAX} caractères maximum.`;
    }

    if (!data.payment_method) {
        errors.payment_method = 'Choisissez un mode de paiement.';
    }

    return errors;
}

function Section({ title, children }) {
    return (
        <section className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <h2 className="mb-3 font-semibold text-gray-900">{title}</h2>
            {children}
        </section>
    );
}

function OrderSummary({ cart }) {
    return (
        <Section title="Récapitulatif">
            <p className="text-sm text-gray-600">
                Boutique : <span className="font-medium">{cart.store.name}</span>
            </p>
            <ul className="mt-3 divide-y divide-gray-100 text-sm">
                {cart.items.map((item) => (
                    <li
                        key={item.product_id}
                        className="flex justify-between gap-3 py-2"
                    >
                        <span className="min-w-0 text-gray-700">
                            <span className="font-medium">{item.quantity} ×</span>{' '}
                            {item.name}
                        </span>
                        <span className="shrink-0 text-gray-900">
                            {formatPrice(item.price * item.quantity)}
                        </span>
                    </li>
                ))}
            </ul>
            <div className="mt-2 flex items-center justify-between border-t border-gray-200 pt-3">
                <span className="font-medium text-gray-700">Total</span>
                <span className="text-lg font-bold text-gray-900">
                    {formatPrice(cart.total)}
                </span>
            </div>
            <Link
                href={route('cart')}
                className="mt-3 inline-block text-sm font-medium text-emerald-700 hover:underline"
            >
                Modifier le panier
            </Link>
        </Section>
    );
}

export default function Index({ neighborhoods, paymentMethods }) {
    const cart = useCart();
    const { user } = usePage().props.auth;
    const { data, setData, errors, setError, clearErrors, processing, post, transform } = useForm({
        neighborhood_id: '',
        address_landmarks: '',
        payment_method: '',
    });

    const update = (field, value) => {
        setData(field, value);
        clearErrors(field);
    };

    const submit = (e) => {
        e.preventDefault();

        const clientErrors = validate(data);
        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);
            // Amène le premier champ en erreur à l'écran (utile sur mobile).
            document
                .getElementById(Object.keys(clientErrors)[0])
                ?.focus();
            return;
        }

        // Seuls les identifiants et quantités sont envoyés : le serveur relit les prix.
        transform((formData) => ({
            ...formData,
            items: cart.items.map((item) => ({
                product_id: item.product_id,
                quantity: item.quantity,
            })),
        }));

        post(route('orders.store'), {
            // Panier vidé seulement une fois la commande enregistrée.
            onSuccess: () => cart.clearCart(),
        });
    };

    // Erreurs serveur liées au panier (panier vide, produit supprimé, plusieurs boutiques…).
    const cartError =
        errors.items ??
        Object.entries(errors).find(([key]) => key.startsWith('items.'))?.[1];

    if (cart.items.length === 0) {
        return (
            <PublicLayout>
                <Head title="Commande" />
                <div className="mx-auto mt-16 max-w-md text-center">
                    <h1 className="text-xl font-bold text-gray-900">
                        Votre panier est vide
                    </h1>
                    <p className="mt-2 text-gray-600">
                        Ajoutez des articles avant de passer commande.
                    </p>
                    <Link
                        href={route('home')}
                        className="mt-6 inline-block rounded-full bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                    >
                        Voir les boutiques
                    </Link>
                </div>
            </PublicLayout>
        );
    }

    return (
        <PublicLayout>
            <Head title="Commande" />

            <h1 className="mt-6 text-2xl font-bold text-gray-900">
                Finaliser la commande
            </h1>

            <form
                onSubmit={submit}
                noValidate
                className="mt-4 grid gap-4 lg:grid-cols-[1fr_380px] lg:items-start"
            >
                {/* Sur mobile, le récapitulatif passe en premier. */}
                <div className="lg:order-2 lg:sticky lg:top-20">
                    <OrderSummary cart={cart} />
                </div>

                <div className="space-y-4 lg:order-1">
                    <Section title="Adresse de livraison">
                        <label
                            htmlFor="neighborhood_id"
                            className="block text-sm font-medium text-gray-700"
                        >
                            Quartier <span className="text-red-600">*</span>
                        </label>
                        <select
                            id="neighborhood_id"
                            value={data.neighborhood_id}
                            onChange={(e) => update('neighborhood_id', e.target.value)}
                            aria-invalid={!!errors.neighborhood_id}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="">Sélectionnez votre quartier</option>
                            {neighborhoods.map((neighborhood) => (
                                <option key={neighborhood.id} value={neighborhood.id}>
                                    {neighborhood.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.neighborhood_id} className="mt-1" />

                        <label
                            htmlFor="address_landmarks"
                            className="mt-4 block text-sm font-medium text-gray-700"
                        >
                            Repères pour le livreur{' '}
                            <span className="text-red-600">*</span>
                        </label>
                        <textarea
                            id="address_landmarks"
                            rows={3}
                            maxLength={LANDMARKS_MAX}
                            value={data.address_landmarks}
                            onChange={(e) => update('address_landmarks', e.target.value)}
                            placeholder="Ex : près de la pharmacie Awendjé, portail bleu"
                            aria-invalid={!!errors.address_landmarks}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        />
                        <div className="mt-1 flex justify-between gap-2">
                            <InputError message={errors.address_landmarks} />
                            <span className="ml-auto shrink-0 text-xs text-gray-400">
                                {data.address_landmarks.length}/{LANDMARKS_MAX}
                            </span>
                        </div>

                        {user.phone && (
                            <p className="mt-3 text-sm text-gray-500">
                                Le livreur pourra vous joindre au{' '}
                                <span className="font-medium text-gray-700">
                                    {user.phone}
                                </span>
                                .
                            </p>
                        )}
                    </Section>

                    <Section title="Mode de paiement">
                        <fieldset>
                            <legend className="sr-only">Mode de paiement</legend>
                            <div id="payment_method" tabIndex={-1} className="space-y-2 focus:outline-none">
                                {paymentMethods.map((method) => (
                                    <label
                                        key={method.value}
                                        className={`flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition ${
                                            data.payment_method === method.value
                                                ? 'border-emerald-600 bg-emerald-50'
                                                : 'border-gray-200 hover:border-gray-300'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value={method.value}
                                            checked={data.payment_method === method.value}
                                            onChange={(e) => update('payment_method', e.target.value)}
                                            className="text-emerald-600 focus:ring-emerald-500"
                                        />
                                        <span className="font-medium text-gray-800">
                                            {method.label}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                        <InputError message={errors.payment_method} className="mt-1" />
                        <p className="mt-3 text-xs text-gray-500">
                            Aucun paiement n'est effectué en ligne sur Gogab :
                            seul votre choix est enregistré avec la commande.
                        </p>
                    </Section>

                    {cartError && (
                        <div
                            role="alert"
                            className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                        >
                            {cartError}{' '}
                            <Link href={route('cart')} className="font-medium underline">
                                Voir mon panier
                            </Link>
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-full bg-emerald-600 py-3 font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 disabled:opacity-50"
                    >
                        {processing
                            ? 'Envoi en cours…'
                            : `Confirmer la commande · ${formatPrice(cart.total)}`}
                    </button>
                </div>
            </form>
        </PublicLayout>
    );
}
