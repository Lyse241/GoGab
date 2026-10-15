import StarRating from '@/Components/Reviews/StarRating';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/UI/Pagination';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head } from '@inertiajs/react';
import { Star } from 'lucide-react';

/**
 * Avis reçus par le commerce : moyenne, répartition des notes et commentaires des clients
 * (prénom seulement ; un commentaire masqué par l'admin est signalé, sa note reste comptée).
 */
export default function Index({ summary, reviews }) {
    return (
        <DashboardLayout header={<h1 className="text-xl font-bold text-secondary-900">Avis des clients</h1>}>
            <Head title="Avis des clients" />

            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                {summary.count === 0 ? (
                    <EmptyState
                        icon={Star}
                        title="Aucun avis pour l’instant"
                        description="Les clients peuvent noter votre commerce une fois leur commande livrée."
                    />
                ) : (
                    <>
                        <Card>
                            <div className="flex flex-wrap items-center gap-6">
                                <div>
                                    <p className="text-4xl font-bold text-secondary-900">{summary.average.toLocaleString('fr-FR')}</p>
                                    <StarRating value={summary.average} />
                                    <p className="mt-1 text-sm text-gray-500">
                                        {summary.count} avis
                                    </p>
                                </div>
                                <ul className="min-w-[12rem] flex-1 space-y-1.5" aria-label="Répartition des notes">
                                    {summary.distribution.map(({ stars, count }) => (
                                        <li key={stars} className="flex items-center gap-2 text-sm">
                                            <span className="w-10 shrink-0 text-gray-600">{stars} ★</span>
                                            <span className="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                                                <span
                                                    className="block h-full rounded-full bg-primary-600"
                                                    style={{ width: `${(count / summary.count) * 100}%` }}
                                                />
                                            </span>
                                            <span className="w-8 shrink-0 text-right text-gray-500">{count}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </Card>

                        <Card padding="none">
                            <ul className="divide-y divide-gray-100">
                                {reviews.data.map((review) => (
                                    <li key={review.id} className="px-4 py-3">
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="font-medium text-gray-900">{review.client}</p>
                                            <span className="text-xs text-gray-500">{review.date}</span>
                                        </div>
                                        <StarRating value={review.rating} size="sm" className="mt-1" />
                                        {review.comment && <p className="mt-1.5 text-sm text-gray-700">{review.comment}</p>}
                                        {review.comment_hidden && (
                                            <p className="mt-1.5 text-sm italic text-gray-500">
                                                Commentaire masqué par l’équipe Gogab.
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </Card>
                        <Pagination paginator={reviews} />
                    </>
                )}
            </div>
        </DashboardLayout>
    );
}
