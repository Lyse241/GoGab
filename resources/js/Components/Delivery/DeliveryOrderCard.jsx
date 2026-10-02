import Card from '@/Components/UI/Card';
import StatusBadge from '@/Components/UI/StatusBadge';
import { formatFCFA } from '@/utils/format';
import { MapPin, Phone, Store, Wallet } from 'lucide-react';

/**
 * Carte d'une course pour le livreur (offre ou course en cours) : retrait, livraison, paiement,
 * articles ; client et téléphone uniquement après acceptation. `children` = bouton d'action.
 */
export default function DeliveryOrderCard({ order, children }) {
    const itemCount = order.items.reduce((sum, item) => sum + item.quantity, 0);

    return (
        <Card className="flex flex-col gap-3">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="font-bold text-secondary-900">{order.number}</p>
                    <p className="text-xs text-gray-500">Passée le {order.created_at}</p>
                </div>
                <p className="text-lg font-bold text-gray-900">{formatFCFA(order.total_price)}</p>
            </div>

            {order.status !== 'en_recherche_livreur' && <StatusBadge status={order.status} size="sm" className="self-start" />}

            <ul className="space-y-2 text-sm">
                <li className="flex items-start gap-2">
                    <Store className="mt-0.5 h-4 w-4 shrink-0 text-primary-600" aria-hidden="true" />
                    <span>
                        Récupérer chez <span className="font-semibold text-gray-900">{order.store}</span>
                        {order.store_neighborhood && <span className="text-gray-600"> · {order.store_neighborhood}</span>}
                    </span>
                </li>
                <li className="flex items-start gap-2">
                    <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-secondary" aria-hidden="true" />
                    <span>
                        Livrer à <span className="font-semibold text-gray-900">{order.neighborhood}</span>
                        {order.address_landmarks && <span className="block whitespace-pre-line text-gray-600">{order.address_landmarks}</span>}
                    </span>
                </li>
                <li className="flex items-start gap-2">
                    <Wallet className="mt-0.5 h-4 w-4 shrink-0 text-gray-500" aria-hidden="true" />
                    <span className="text-gray-700">
                        {order.payment_method_label}
                        {order.cash_given && ` · le client remet ${formatFCFA(order.cash_given)}`} · {itemCount} article{itemCount > 1 ? 's' : ''}
                    </span>
                </li>
                {order.client && (
                    <li className="flex items-center gap-2">
                        <Phone className="h-4 w-4 shrink-0 text-gray-500" aria-hidden="true" />
                        <span className="font-semibold text-gray-900">{order.client.name}</span>
                        {order.client.phone && (
                            <a href={`tel:${order.client.phone.replace(/\s/g, '')}`} className="inline-flex min-h-tap items-center text-secondary underline">
                                {order.client.phone}
                            </a>
                        )}
                    </li>
                )}
            </ul>

            <details className="text-sm">
                <summary className="min-h-tap cursor-pointer py-2 text-gray-600 hover:text-gray-900">Détail des articles</summary>
                <ul className="space-y-1 rounded-xl bg-gray-50 px-3 py-2 text-gray-700">
                    {order.items.map((item) => (
                        <li key={item.id}>
                            <span className="font-semibold">{item.quantity} ×</span> {item.name}
                        </li>
                    ))}
                </ul>
            </details>

            {children}
        </Card>
    );
}
