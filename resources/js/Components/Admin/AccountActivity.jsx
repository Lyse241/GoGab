import Badge from '@/Components/UI/Badge';
import Card, { CardHeader } from '@/Components/UI/Card';
import { formatFCFA, imageUrl } from '@/utils/format';
import { Package, ReceiptText } from 'lucide-react';

const TITLES = {
    business: { orders: 'Commandes récentes', empty: 'Aucune commande reçue pour le moment.' },
    delivery: { orders: 'Courses récentes', empty: 'Aucune course effectuée pour le moment.' },
    client: { orders: 'Commandes récentes', empty: 'Aucune commande passée pour le moment.' },
};

/**
 * Activité d'un compte sur sa fiche admin : produits du commerce (entreprise)
 * et commandes récentes (reçues, livrées ou passées selon le rôle).
 */
export default function AccountActivity({ role, activity }) {
    const titles = TITLES[role];

    if (!titles) {
        return null;
    }

    const { products, recent_orders: orders, orders_count: ordersCount } = activity;

    return (
        <div className="space-y-6">
            {products && (
                <Card>
                    <CardHeader
                        title="Produits"
                        description={
                            products.count === 0
                                ? 'Catalogue vide'
                                : `${products.count} produit${products.count > 1 ? 's' : ''} · ${products.available} disponible${products.available > 1 ? 's' : ''}`
                        }
                        action={<Package className="h-5 w-5 text-primary-600" aria-hidden="true" />}
                    />
                    {products.count === 0 ? (
                        <p className="text-sm text-gray-500">L’entreprise n’a encore ajouté aucun produit.</p>
                    ) : (
                        <>
                            <ul className="grid gap-2 sm:grid-cols-2">
                                {products.items.map((product) => (
                                    <li key={product.id} className="flex items-center gap-3 rounded-xl p-2 ring-1 ring-gray-100">
                                        <div className="h-11 w-11 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                                            {product.image && (
                                                <img src={imageUrl(product.image)} alt="" loading="lazy" className="h-full w-full object-cover" />
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-gray-900">{product.name}</p>
                                            <p className="text-xs text-gray-500">{formatFCFA(product.price)}</p>
                                        </div>
                                        {!product.is_available && (
                                            <Badge color="neutral" size="sm">
                                                Indisponible
                                            </Badge>
                                        )}
                                    </li>
                                ))}
                            </ul>
                            {products.count > products.items.length && (
                                <p className="mt-2 text-xs text-gray-500">
                                    … et {products.count - products.items.length} autre(s).
                                </p>
                            )}
                        </>
                    )}
                </Card>
            )}

            <Card>
                <CardHeader
                    title={titles.orders}
                    description={ordersCount > 0 ? `${ordersCount} au total` : undefined}
                    action={<ReceiptText className="h-5 w-5 text-primary-600" aria-hidden="true" />}
                />
                {orders.length === 0 ? (
                    <p className="text-sm text-gray-500">{titles.empty}</p>
                ) : (
                    <ul className="divide-y divide-gray-100">
                        {orders.map((order) => (
                            <li key={order.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5">
                                <div className="min-w-0">
                                    <p className="text-sm font-semibold text-gray-900">
                                        {order.reference}
                                        <span className="font-normal text-gray-500">
                                            {' · '}
                                            {role === 'business' ? order.client : order.store}
                                            {role === 'delivery' && order.neighborhood && ` → ${order.neighborhood}`}
                                        </span>
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        {order.created_at} · {formatFCFA(order.total_price)}
                                    </p>
                                </div>
                                <Badge color={order.status_color} size="sm">
                                    {order.status_label}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </div>
    );
}
