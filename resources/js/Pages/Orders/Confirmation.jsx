import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatFCFA } from '@/utils/format';
import { Head } from '@inertiajs/react';
import { Check, Store } from 'lucide-react';

/**
 * Confirmation juste après la commande : numéro, commerce, total, rappel du paiement.
 * Seul le panier de ce commerce a été vidé (les autres paniers restent intacts).
 */
export default function Confirmation({ order }) {
    return (
        <PublicLayout search={false}>
            <Head title={`Commande ${order.number} enregistrée`} />

            <div className="mx-auto max-w-lg pt-10 text-center">
                <span className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-primary-600">
                    <Check className="h-9 w-9" strokeWidth={2.5} aria-hidden="true" />
                </span>
                <h1 className="mt-4 text-2xl font-bold text-secondary-900">Merci, votre commande est envoyée !</h1>
                <p className="mt-2 text-gray-600">
                    {order.store} va l’accepter dans quelques instants : vous serez prévenu à chaque étape.
                </p>

                <Card className="mt-6 text-left">
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-600">Numéro de commande</dt>
                            <dd className="font-bold text-secondary-900">{order.number}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-600">Commerce</dt>
                            <dd className="font-medium text-gray-900">{order.store}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-600">Paiement</dt>
                            <dd className="font-medium text-gray-900">{order.payment_method_label}</dd>
                        </div>
                        <div className="flex justify-between gap-4 border-t border-gray-100 pt-3">
                            <dt className="font-semibold text-gray-900">Total</dt>
                            <dd className="text-lg font-bold text-gray-900">{formatFCFA(order.total_price)}</dd>
                        </div>
                        {order.change_due !== null && (
                            <div className="rounded-xl bg-primary-50 px-3 py-2 text-primary-800">
                                Vous payerez {formatFCFA(order.cash_given)} : le livreur vous rendra {formatFCFA(order.change_due)}.
                            </div>
                        )}
                    </dl>
                </Card>

                <div className="mt-6 grid gap-2">
                    <Button href={route('orders.show', order.id)} size="lg" fullWidth>
                        Suivre ma commande
                    </Button>
                    <Button href={route('stores.show', order.store_id)} variant="ghost" icon={Store} fullWidth>
                        Retour au commerce
                    </Button>
                </div>
            </div>
        </PublicLayout>
    );
}
