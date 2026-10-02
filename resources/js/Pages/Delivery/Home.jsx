import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import StatusBadge from '@/Components/UI/StatusBadge';
import DeliveryLayout from '@/Layouts/DeliveryLayout';
import { formatFCFA } from '@/utils/format';
import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import { ArrowRight, Bike, ChevronRight, MapPin, Megaphone, PackageCheck, Store, Wallet } from 'lucide-react';

function Stat({ icon: Icon, label, value }) {
    return (
        <Card padding="sm" className="flex items-center gap-3 px-4">
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-700">
                <Icon className="h-5 w-5" aria-hidden="true" />
            </span>
            <div className="min-w-0">
                <p className="text-xs font-medium text-gray-500">{label}</p>
                <p className="truncate text-xl font-bold text-secondary-900">{value}</p>
            </div>
        </Card>
    );
}

/**
 * Accueil du livreur : disponibilité (en-tête), zone d'activité, course en cours, compteurs du
 * jour (courses livrées, gains = frais de livraison).
 */
export default function Home({ current, stats }) {
    const { auth, courier, badges = {} } = usePage().props;
    const firstName = auth.user.name.split(' ')[0];

    usePoll(15000, { only: ['current', 'stats', 'badges', 'courier'] });

    return (
        <DeliveryLayout title={`Bonjour ${firstName}`} subtitle="Votre journée en un coup d’œil">
            <Head title="Espace livreur" />

            {/* Zone d'activité */}
            <Card padding="sm" className="flex items-center gap-3 px-4">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-secondary-50 text-secondary">
                    <MapPin className="h-5 w-5" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-medium text-gray-500">Zone d’activité</p>
                    {courier?.zone ? (
                        <p className="truncate font-semibold text-secondary-900">
                            Zone {courier.zone} <span className="font-normal text-gray-600">· base : {courier.base_neighborhood}</span>
                        </p>
                    ) : (
                        <p className="font-semibold text-danger-700">Aucun quartier de base</p>
                    )}
                </div>
                <Link
                    href={route('delivery.profile')}
                    className="inline-flex min-h-tap items-center gap-1 rounded-lg px-2 text-sm font-semibold text-secondary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                >
                    Changer
                    <ChevronRight className="h-4 w-4" aria-hidden="true" />
                </Link>
            </Card>

            {/* Course en cours */}
            {current ? (
                <Card className="ring-2 ring-primary-200">
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                            <p className="text-xs font-semibold uppercase tracking-wide text-primary-700">Course en cours</p>
                            <p className="font-bold text-secondary-900">{current.number}</p>
                        </div>
                        <StatusBadge status={current.status} size="sm" />
                    </div>
                    <ol className="mt-3 space-y-2 text-sm">
                        <li className="flex items-start gap-2">
                            <Store className="mt-0.5 h-4 w-4 shrink-0 text-primary-600" aria-hidden="true" />
                            <span>
                                <span className="font-semibold text-gray-900">{current.store}</span>
                                {current.store_neighborhood && <span className="text-gray-600"> · {current.store_neighborhood}</span>}
                            </span>
                        </li>
                        <li className="flex items-start gap-2">
                            <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-secondary" aria-hidden="true" />
                            <span>
                                Livrer à <span className="font-semibold text-gray-900">{current.neighborhood}</span>
                            </span>
                        </li>
                        <li className="flex items-start gap-2">
                            <Wallet className="mt-0.5 h-4 w-4 shrink-0 text-gray-500" aria-hidden="true" />
                            <span className="text-gray-700">
                                {current.payment_method_label} · {formatFCFA(current.total_price)}
                            </span>
                        </li>
                    </ol>
                    <Button href={route('delivery.current')} iconRight={ArrowRight} fullWidth className="mt-4" size="lg">
                        Continuer la course
                    </Button>
                </Card>
            ) : (
                <Card className="flex flex-col items-center gap-3 py-6 text-center">
                    <span className="flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                        <Bike className="h-7 w-7" aria-hidden="true" />
                    </span>
                    <div>
                        <p className="font-semibold text-secondary-900">Aucune course en cours</p>
                        <p className="text-sm text-gray-500">
                            {courier?.is_available
                                ? badges.offers
                                    ? `${badges.offers} offre${badges.offers > 1 ? 's' : ''} dans votre zone.`
                                    : 'Aucune offre dans votre zone pour le moment.'
                                : 'Passez disponible pour recevoir des offres.'}
                        </p>
                    </div>
                    <Button href={route('delivery.offers')} variant="secondary" icon={Megaphone}>
                        Voir les offres
                    </Button>
                </Card>
            )}

            {/* Compteurs du jour */}
            <section aria-labelledby="today-title">
                <h2 id="today-title" className="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Aujourd’hui
                </h2>
                <div className="grid grid-cols-2 gap-3">
                    <Stat icon={PackageCheck} label="Courses livrées" value={stats.deliveries} />
                    <Stat icon={Wallet} label="Gains" value={formatFCFA(stats.earnings)} />
                </div>
            </section>
        </DeliveryLayout>
    );
}
