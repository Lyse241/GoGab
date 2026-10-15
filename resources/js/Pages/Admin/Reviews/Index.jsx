import StarRating from '@/Components/Reviews/StarRating';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/UI/Pagination';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head, router } from '@inertiajs/react';
import { Eye, EyeOff, Star } from 'lucide-react';
import { useState } from 'react';

const FILTERS = [
    { value: 'comments', label: 'Avec commentaire' },
    { value: 'hidden', label: 'Masqués' },
    { value: 'all', label: 'Tous' },
];

/**
 * Modération des avis : l'admin masque le commentaire d'un avis abusif (la note reste comptée
 * dans la moyenne du commerce) ou le réaffiche.
 */
export default function Index({ filter, reviews }) {
    const [busy, setBusy] = useState(null);

    const toggle = (review) => {
        setBusy(review.id);
        router.patch(
            route('admin.reviews.visibility', review.id),
            { hidden: !review.hidden },
            { preserveScroll: true, onFinish: () => setBusy(null) },
        );
    };

    return (
        <DashboardLayout header={<h1 className="text-xl font-bold text-secondary-900">Avis des clients</h1>}>
            <Head title="Avis des clients" />

            <div className="mx-auto max-w-4xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                <nav className="flex flex-wrap gap-2" aria-label="Filtrer les avis">
                    {FILTERS.map((item) => (
                        <Button
                            key={item.value}
                            href={route('admin.reviews.index', { filter: item.value })}
                            size="sm"
                            variant={filter === item.value ? 'primary' : 'outline'}
                            aria-current={filter === item.value ? 'page' : undefined}
                        >
                            {item.label}
                        </Button>
                    ))}
                </nav>

                {reviews.data.length === 0 ? (
                    <EmptyState icon={Star} title="Aucun avis" description="Rien à afficher pour ce filtre." />
                ) : (
                    <Card padding="none">
                        <ul className="divide-y divide-gray-100">
                            {reviews.data.map((review) => (
                                <li key={review.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start">
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm text-gray-600">
                                            <span className="font-medium text-gray-900">{review.client}</span> sur{' '}
                                            <span className="font-medium text-gray-900">{review.store}</span> · {review.date}
                                        </p>
                                        <div className="mt-1 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                                            <span className="inline-flex items-center gap-1">
                                                Commerce <StarRating value={review.store_rating} size="sm" />
                                            </span>
                                            {review.courier_rating && (
                                                <span className="inline-flex items-center gap-1">
                                                    Livreur <StarRating value={review.courier_rating} size="sm" />
                                                </span>
                                            )}
                                            {review.hidden && (
                                                <Badge size="sm" variant="warning">
                                                    Masqué
                                                </Badge>
                                            )}
                                        </div>
                                        {review.comment && (
                                            <p className={review.hidden ? 'mt-1.5 text-sm text-gray-500 line-through' : 'mt-1.5 text-sm text-gray-800'}>
                                                {review.comment}
                                            </p>
                                        )}
                                    </div>
                                    {review.comment && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            icon={review.hidden ? Eye : EyeOff}
                                            loading={busy === review.id}
                                            onClick={() => toggle(review)}
                                        >
                                            {review.hidden ? 'Réafficher' : 'Masquer'}
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
                <Pagination paginator={reviews} />
            </div>
        </DashboardLayout>
    );
}
