import OpeningStatusBadge from '@/Components/OpeningStatusBadge';
import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import Switch from '@/Components/UI/Switch';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { cn } from '@/utils/cn';
import { imageUrl } from '@/utils/format';
import { Head, router, usePage } from '@inertiajs/react';
import { Clock, Package, Pencil, ReceiptText, Store } from 'lucide-react';
import { useState } from 'react';

const formatHour = (time) => time.replace(':', 'h');

function todayHours(today) {
    if (today.is_closed || !today.opens_at || !today.closes_at) {
        return 'Fermé toute la journée';
    }

    if (today.opens_at === today.closes_at) {
        return 'Ouvert 24 h/24';
    }

    return `${formatHour(today.opens_at)} – ${formatHour(today.closes_at)}`;
}

/**
 * Tableau de bord entreprise (provisoire) : bienvenue, interrupteur d'ouverture, état du moment.
 * Le commerce n'est réellement ouvert que si l'interrupteur est sur « ouvert » ET qu'on est
 * dans ses horaires : l'état affiché vient du serveur (heure de Libreville).
 */
export default function Dashboard({ store, today, productsCount }) {
    const { auth } = usePage().props;
    const [saving, setSaving] = useState(false);

    const toggle = (open) =>
        router.patch(
            route('business.store.open'),
            { is_open: open },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );

    const firstName = auth.user.name.split(' ')[0];

    return (
        <DashboardLayout header={<h1 className="truncate text-xl font-bold text-secondary-900">{store.name}</h1>}>
            <Head title="Tableau de bord" />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
                {/* Bienvenue */}
                <Card padding="none" className="overflow-hidden">
                    <div className="relative h-28 bg-gradient-to-br from-primary-600 to-secondary sm:h-36">
                        {store.cover_image && (
                            <img src={imageUrl(store.cover_image)} alt="" className="h-full w-full object-cover" />
                        )}
                        <div className="absolute inset-0 bg-gradient-to-t from-secondary-900/60 to-transparent" aria-hidden="true" />
                    </div>
                    <div className="flex items-end gap-4 px-4 pb-4 sm:px-5">
                        <div className="relative z-10 -mt-10 flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-md ring-4 ring-white">
                            {store.logo ? (
                                <img src={imageUrl(store.logo)} alt={`Logo de ${store.name}`} className="h-full w-full object-cover" />
                            ) : (
                                <Store className="h-8 w-8 text-primary-600" aria-hidden="true" />
                            )}
                        </div>
                        <div className="min-w-0 pt-3">
                            <p className="text-lg font-bold text-secondary-900">Bonjour {firstName} 👋</p>
                            <p className="truncate text-sm text-gray-600">
                                {[store.category, store.neighborhood].filter(Boolean).join(' · ')}
                            </p>
                        </div>
                    </div>
                </Card>

                {/* Interrupteur ouvert / fermé */}
                <Card
                    className={cn(
                        'ring-2 transition-colors',
                        store.is_open ? 'ring-primary-200' : 'ring-gray-200 bg-gray-50',
                    )}
                >
                    <Switch
                        size="lg"
                        checked={store.is_open}
                        onChange={toggle}
                        loading={saving}
                        label={<span className="text-lg">{store.is_open ? 'Commerce ouvert' : 'Commerce fermé'}</span>}
                        description={
                            store.is_open
                                ? 'Les clients peuvent commander pendant vos horaires.'
                                : 'Fermeture temporaire : aucune commande possible, même pendant vos horaires.'
                        }
                    />
                    <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-gray-100 pt-4">
                        <span className="text-sm text-gray-600">En ce moment :</span>
                        <OpeningStatusBadge isOpen={store.is_open_now} detail={store.status_detail} />
                    </div>
                </Card>

                {/* Horaires du jour */}
                <Card>
                    <CardHeader
                        title={`Aujourd’hui (${today.label.toLowerCase()})`}
                        action={<Clock className="h-5 w-5 text-primary-600" aria-hidden="true" />}
                    />
                    <p className="text-2xl font-bold text-secondary-900">{todayHours(today)}</p>
                    <Button href={route('business.store.edit')} variant="ghost" size="sm" icon={Pencil} className="-ml-3 mt-2">
                        Modifier mes horaires
                    </Button>
                </Card>

                {/* Raccourcis */}
                <div className="grid gap-3 sm:grid-cols-2">
                    <Card href={route('business.orders.index')} className="flex items-center gap-3">
                        <ReceiptText className="h-6 w-6 text-primary-600" aria-hidden="true" />
                        <span>
                            <span className="block font-semibold text-secondary-900">Commandes</span>
                            <span className="block text-sm text-gray-500">Bientôt disponible</span>
                        </span>
                    </Card>
                    <Card href={route('business.products.index')} className="flex items-center gap-3">
                        <Package className="h-6 w-6 text-primary-600" aria-hidden="true" />
                        <span>
                            <span className="block font-semibold text-secondary-900">Produits</span>
                            <span className="block text-sm text-gray-500">
                                {productsCount} produit{productsCount > 1 ? 's' : ''} au catalogue
                            </span>
                        </span>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}
