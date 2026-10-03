import { StoreThumb } from '@/Components/Cart/CartList';
import { atStore } from '@/Components/Cart/CartPanel';
import FormErrors, { focusFirstError } from '@/Components/FormErrors';
import LazyImage from '@/Components/LazyImage';
import NeighborhoodSelect from '@/Components/NeighborhoodSelect';
import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Input from '@/Components/UI/Input';
import Textarea from '@/Components/UI/Textarea';
import { useCart } from '@/Contexts/CartContext';
import { useNeighborhood } from '@/Contexts/NeighborhoodContext';
import useStoreStatus from '@/Hooks/useStoreStatus';
import PublicLayout from '@/Layouts/PublicLayout';
import { cartTotals } from '@/utils/cartTotals';
import { cn } from '@/utils/cn';
import { formatFCFA, imageUrl } from '@/utils/format';
import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Banknote, Check, CircleAlert, Clock, Hourglass, MapPin, ShoppingBag, Smartphone } from 'lucide-react';

const LANDMARKS_MIN = 10;

/**
 * Validation côté client (le serveur revalide tout à l'envoi).
 */
function validate(data, total) {
    const errors = {};

    if (!data.neighborhood_id) {
        errors.neighborhood_id = 'Choisissez votre quartier de livraison.';
    }
    if (data.address_landmarks.trim().length < LANDMARKS_MIN) {
        errors.address_landmarks = data.address_landmarks.trim()
            ? 'Soyez un peu plus précis (10 caractères minimum).'
            : 'Indiquez des repères pour que le livreur trouve votre adresse.';
    }
    if (!data.payment_method) {
        errors.payment_method = 'Choisissez un mode de paiement.';
    }
    if (data.payment_method === 'cash') {
        const cash = Number(data.cash_given);
        if (!data.cash_given) {
            errors.cash_given = 'Indiquez avec quel montant vous paierez, pour que le livreur prévoie la monnaie.';
        } else if (cash < total) {
            errors.cash_given = `Le montant remis doit couvrir le total de la commande (${formatFCFA(total)}).`;
        }
    }

    return errors;
}

function PaymentCard({ method, selected, onSelect }) {
    const Icon = method.mobile_money ? Smartphone : Banknote;

    return (
        <label
            className={cn(
                'relative flex cursor-pointer items-start gap-3 rounded-2xl p-4 ring-1 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary',
                selected ? 'bg-primary-50 ring-2 ring-primary-500' : 'bg-white ring-gray-200 hover:ring-gray-300',
            )}
        >
            <input
                type="radio"
                name="payment_method"
                value={method.value}
                checked={selected}
                onChange={() => onSelect(method.value)}
                className="sr-only"
            />
            <span className={cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-xl', selected ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600')}>
                <Icon className="h-5 w-5" aria-hidden="true" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block font-semibold text-secondary-900">{method.label}</span>
                <span className="mt-0.5 block text-sm text-gray-600">{method.hint}</span>
            </span>
            {selected && <Check className="h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" />}
        </label>
    );
}

/**
 * Tunnel de commande du panier d'UN commerce : récapitulatif (lecture seule), note pour le
 * commerce, adresse (préremplie depuis le profil), paiement, montant remis en espèces avec la
 * monnaie à rendre, sous-total + frais + total. Seul le panier de ce commerce est envoyé et vidé.
 */
/**
 * Jeton aléatoire (crypto.randomUUID n'existe qu'en HTTPS ou sur localhost : repli pour un
 * téléphone qui teste l'app sur le réseau local en HTTP).
 */
function newCheckoutToken() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }
    const bytes = window.crypto?.getRandomValues ? window.crypto.getRandomValues(new Uint8Array(16)) : Array.from({ length: 16 }, () => Math.floor(Math.random() * 256));

    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

export default function Index({ store, address, neighborhoods, paymentMethods, deliveryFee, quickCashAmounts, canOrder }) {
    const carts = useCart();
    const cart = carts.cartOf(store.id) ?? { store, items: [] };
    const { neighborhoodId } = useNeighborhood();

    // Vérification légère : commerce ouvert ? produits encore disponibles ?
    const { status } = useStoreStatus(store.id, cart.items.map((item) => item.product_id));
    const isOpen = status ? status.is_open_now : store.is_open_now;
    const statusLabel = status?.status_label ?? store.status_label;
    const unavailableIds = new Set(status?.unavailable_product_ids ?? []);
    const unavailableItems = cart.items.filter((item) => unavailableIds.has(item.product_id));
    const { total: subtotal, count } = cartTotals(cart.items, unavailableIds);
    const total = subtotal + deliveryFee;

    // Adresse du profil, sinon le quartier choisi dans le header.
    const defaultNeighborhood = address.neighborhood_id ?? (neighborhoods.some((n) => n.id === neighborhoodId) ? neighborhoodId : '');

    const { data, setData, errors, setError, clearErrors, processing, post, transform } = useForm({
        neighborhood_id: defaultNeighborhood ? String(defaultNeighborhood) : '',
        address_landmarks: address.address_landmarks ?? '',
        payment_method: '',
        cash_given: '',
        client_note: '',
    });

    // Jeton unique de cette page : le serveur ne crée jamais deux commandes pour le même jeton
    // (double clic, réseau lent, formulaire renvoyé). Le verrou bloque le second clic tout de suite.
    const [checkoutToken] = useState(newCheckoutToken);
    const submitting = useRef(false);

    const update = (field, value) => {
        setData(field, value);
        clearErrors(field);
    };

    const cash = Number(data.cash_given) || 0;
    const changeDue = data.payment_method === 'cash' && cash > 0 ? cash - total : null;
    const blocked = !canOrder || !isOpen || unavailableItems.length > 0 || count === 0;

    const submit = (event) => {
        event.preventDefault();
        if (blocked || submitting.current) {
            return;
        }

        const clientErrors = validate(data, total);
        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);
            focusFirstError(clientErrors);
            return;
        }

        // Seuls les identifiants et quantités sont envoyés : le serveur relit les prix.
        transform((values) => ({
            ...values,
            store_id: store.id,
            checkout_token: checkoutToken,
            cash_given: values.payment_method === 'cash' ? values.cash_given : null,
            items: cart.items.map((item) => ({ product_id: item.product_id, quantity: item.quantity })),
        }));

        submitting.current = true;
        post(route('orders.store'), {
            // Seul le panier de ce commerce est vidé, une fois la commande enregistrée.
            onSuccess: () => carts.clearCart(store.id),
            onFinish: () => {
                submitting.current = false;
            },
            onError: focusFirstError,
        });
    };

    const cartError = errors.items ?? Object.entries(errors).find(([key]) => key.startsWith('items.'))?.[1];

    if (cart.items.length === 0) {
        return (
            <PublicLayout search={false}>
                <Head title="Commande" />
                <EmptyState
                    className="mx-auto mt-10 max-w-lg"
                    icon={ShoppingBag}
                    title={`Votre panier ${atStore(store.name)} est vide`}
                    description="Ajoutez des articles avant de passer commande."
                    action={<Button href={route('stores.show', store.id)}>Retour {atStore(store.name)}</Button>}
                />
            </PublicLayout>
        );
    }

    return (
        <PublicLayout search={false}>
            <Head title={`Commande ${atStore(store.name)}`} />

            <h1 className="mt-6 text-2xl font-bold text-secondary-900">Finaliser la commande</h1>

            <form onSubmit={submit} noValidate className="mt-4 grid gap-5 pb-10 lg:grid-cols-[1fr_24rem] lg:items-start">
                {/* Récapitulatif : en premier sur mobile, colonne collante sur desktop */}
                <div className="space-y-4 lg:sticky lg:top-[calc(var(--header-h,4rem)+1rem)] lg:order-2">
                    <Card>
                        <div className="mb-3 flex items-center gap-3">
                            <StoreThumb store={store} className="h-11 w-11" />
                            <div className="min-w-0">
                                <p className="truncate font-semibold text-secondary-900">{store.name}</p>
                                <p className="text-sm text-gray-500">
                                    {count} article{count > 1 ? 's' : ''}
                                </p>
                            </div>
                        </div>
                        <ul className="divide-y divide-gray-100">
                            {cart.items.map((item) => {
                                const unavailable = unavailableIds.has(item.product_id);

                                return (
                                    <li key={item.product_id} className="flex items-center gap-3 py-2.5">
                                        <LazyImage src={imageUrl(item.image)} alt="" className={cn('h-11 w-11 shrink-0 rounded-lg', unavailable && 'opacity-50 grayscale')} />
                                        <span className={cn('min-w-0 flex-1 text-sm', unavailable ? 'text-gray-500 line-through' : 'text-gray-800')}>
                                            <span className="font-semibold">{item.quantity} ×</span> {item.name}
                                        </span>
                                        <span className={cn('shrink-0 text-sm font-medium', unavailable ? 'text-gray-500 line-through' : 'text-gray-900')}>
                                            {formatFCFA(item.price * item.quantity)}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                        <dl className="mt-3 space-y-1.5 border-t border-gray-100 pt-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-gray-600">Sous-total</dt>
                                <dd className="font-medium text-gray-900">{formatFCFA(subtotal)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-600">Frais de livraison</dt>
                                <dd className="font-medium text-gray-900">{formatFCFA(deliveryFee)}</dd>
                            </div>
                            <div className="flex justify-between border-t border-gray-100 pt-2 text-base">
                                <dt className="font-semibold text-gray-900">Total</dt>
                                <dd className="text-lg font-bold text-gray-900">{formatFCFA(total)}</dd>
                            </div>
                        </dl>
                        <Link href={route('cart', { store: store.id })} className="mt-3 inline-block text-sm font-medium text-secondary hover:underline">
                            Modifier le panier
                        </Link>
                    </Card>
                </div>

                <div className="space-y-4 lg:order-1">
                    <FormErrors errors={cartError ? {} : errors} />

                    <Card>
                        <CardHeader title="Adresse de livraison" action={<MapPin className="h-5 w-5 text-primary-600" aria-hidden="true" />} />
                        <div className="space-y-4">
                            <NeighborhoodSelect
                                id="neighborhood_id"
                                label="Quartier"
                                required
                                neighborhoods={neighborhoods}
                                value={data.neighborhood_id}
                                onChange={(e) => update('neighborhood_id', e.target.value)}
                                error={errors.neighborhood_id}
                            />
                            <Textarea
                                id="address_landmarks"
                                label="Repères pour le livreur"
                                required
                                rows={3}
                                maxLength={500}
                                placeholder="Ex. : près de la pharmacie Awendjé, portail bleu"
                                hint={address.address_landmarks ? 'Prérempli depuis votre profil : modifiable pour cette commande.' : undefined}
                                value={data.address_landmarks}
                                onChange={(e) => update('address_landmarks', e.target.value)}
                                error={errors.address_landmarks}
                            />
                        </div>
                    </Card>

                    <Card>
                        <CardHeader title="Mode de paiement" />
                        <fieldset>
                            <legend className="sr-only">Mode de paiement</legend>
                            <div id="payment_method" tabIndex={-1} className="grid gap-3 focus:outline-none sm:grid-cols-3">
                                {paymentMethods.map((method) => (
                                    <PaymentCard
                                        key={method.value}
                                        method={method}
                                        selected={data.payment_method === method.value}
                                        onSelect={(value) => update('payment_method', value)}
                                    />
                                ))}
                            </div>
                            {errors.payment_method && <p className="mt-2 text-sm text-danger-600">{errors.payment_method}</p>}
                        </fieldset>

                        {data.payment_method === 'cash' && (
                            <div className="mt-5 rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-100">
                                <p id="cash-label" className="font-semibold text-secondary-900">
                                    Avec quel montant paierez-vous ?
                                </p>
                                <p className="mt-0.5 text-sm text-gray-600">Le livreur prévoira la monnaie.</p>
                                <div className="mt-3 flex flex-wrap gap-2" role="group" aria-labelledby="cash-label">
                                    {[{ value: total, label: 'Montant exact' }, ...quickCashAmounts.filter((amount) => amount > total).map((amount) => ({ value: amount, label: formatFCFA(amount) }))].map(({ value, label }) => (
                                        <button
                                            key={label}
                                            type="button"
                                            onClick={() => update('cash_given', String(value))}
                                            aria-pressed={cash === value}
                                            className={cn(
                                                'h-11 rounded-full px-4 text-sm font-semibold ring-1 ring-inset transition',
                                                cash === value ? 'bg-secondary text-white ring-secondary' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-100',
                                            )}
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>
                                <Input
                                    id="cash_given"
                                    label="Autre montant"
                                    inputMode="numeric"
                                    suffix="FCFA"
                                    wrapperClassName="mt-3"
                                    placeholder={String(Math.ceil(total / 1000) * 1000)}
                                    value={data.cash_given}
                                    onChange={(e) => update('cash_given', e.target.value.replace(/\D/g, ''))}
                                    error={errors.cash_given}
                                />
                                {changeDue !== null && (
                                    <p
                                        aria-live="polite"
                                        className={cn('mt-3 rounded-xl px-3 py-2 text-sm font-semibold', changeDue >= 0 ? 'bg-primary-50 text-primary-800' : 'bg-danger-50 text-danger-700')}
                                    >
                                        {changeDue >= 0
                                            ? `Monnaie à rendre : ${formatFCFA(changeDue)}`
                                            : `Montant insuffisant : il manque ${formatFCFA(-changeDue)}`}
                                    </p>
                                )}
                            </div>
                        )}
                    </Card>

                    <Card>
                        <Textarea
                            id="client_note"
                            label="Note pour le commerce (facultatif)"
                            rows={2}
                            maxLength={500}
                            placeholder="Ex. : sans piment, bien cuit…"
                            value={data.client_note}
                            onChange={(e) => update('client_note', e.target.value)}
                            error={errors.client_note}
                        />
                    </Card>

                    {cartError && (
                        <div role="alert" className="rounded-2xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800">
                            {cartError}{' '}
                            <Link href={route('cart', { store: store.id })} className="font-semibold underline">
                                Voir mon panier
                            </Link>
                        </div>
                    )}

                    {!isOpen && (
                        <div role="status" className="flex items-start gap-3 rounded-2xl border border-accent-300 bg-accent-50 p-4 text-sm">
                            <Clock className="mt-0.5 h-5 w-5 shrink-0 text-accent-700" aria-hidden="true" />
                            <p>
                                <span className="font-semibold text-secondary-900">{statusLabel}</span> — votre panier est conservé : vous pourrez
                                commander à la réouverture.
                            </p>
                        </div>
                    )}

                    {unavailableItems.length > 0 && (
                        <div role="alert" className="flex items-start gap-3 rounded-2xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800">
                            <CircleAlert className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                            <p>
                                <span className="font-semibold">Plus disponible : {unavailableItems.map((item) => item.name).join(', ')}.</span>{' '}
                                <Link href={route('cart', { store: store.id })} className="font-semibold underline">
                                    Mettre à jour mon panier
                                </Link>
                            </p>
                        </div>
                    )}

                    {canOrder ? (
                        <Button type="submit" size="lg" fullWidth loading={processing} disabled={blocked}>
                            {!isOpen ? 'Commerce fermé' : `Commander · ${formatFCFA(total)}`}
                        </Button>
                    ) : (
                        // Compte non validé : message à la place du bouton.
                        <div role="status" className="flex items-start gap-3 rounded-2xl border border-accent-300 bg-accent-50 p-4 text-sm">
                            <Hourglass className="mt-0.5 h-5 w-5 shrink-0 text-accent-700" aria-hidden="true" />
                            <p className="text-gray-700">
                                <span className="font-semibold text-secondary-900">Votre compte est en attente de validation.</span> Vous pourrez
                                commander dès que l’équipe Gogab l’aura validé ; votre panier est conservé.{' '}
                                <Link href={route('dashboard')} className="font-semibold text-secondary underline">
                                    Suivre mon inscription
                                </Link>
                            </p>
                        </div>
                    )}
                    <p className="text-center text-xs text-gray-500">
                        Aucun paiement n’est effectué en ligne : seul votre choix est enregistré avec la commande.
                    </p>
                </div>
            </form>
        </PublicLayout>
    );
}
