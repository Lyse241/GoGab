import StarRating from '@/Components/Reviews/StarRating';
import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import Textarea from '@/Components/UI/Textarea';
import { useForm } from '@inertiajs/react';

/**
 * Avis du client sur une commande livrée : formulaire tant qu'elle n'a pas été notée
 * (commerce, livreur s'il y en a un, commentaire facultatif), puis rappel de sa note.
 */
export default function ReviewCard({ order, canReview, review }) {
    const form = useForm({ store_rating: 0, courier_rating: 0, comment: '' });

    if (review) {
        return (
            <Card>
                <h2 className="font-semibold text-secondary-900">Votre avis</h2>
                <div className="mt-2 space-y-1 text-sm text-gray-700">
                    <p className="flex items-center gap-2">
                        {order.store.name} <StarRating value={review.store_rating} size="sm" />
                    </p>
                    {review.courier_rating && (
                        <p className="flex items-center gap-2">
                            Livreur <StarRating value={review.courier_rating} size="sm" />
                        </p>
                    )}
                    {review.comment && <p className="pt-1 text-gray-600">« {review.comment} »</p>}
                </div>
            </Card>
        );
    }

    if (!canReview) return null;

    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            store_rating: data.store_rating || '',
            courier_rating: order.courier ? data.courier_rating || '' : null,
        }));
        form.post(route('orders.review', order.id), { preserveScroll: true });
    };

    return (
        <Card>
            <h2 className="font-semibold text-secondary-900">Comment s’est passée votre commande ?</h2>
            <form onSubmit={submit} className="mt-3 space-y-4" noValidate>
                <div>
                    <p className="mb-1 text-sm font-medium text-gray-700">{order.store.name}</p>
                    <StarRating
                        size="lg"
                        label={`Note de ${order.store.name}`}
                        value={form.data.store_rating}
                        onChange={(n) => form.setData('store_rating', n)}
                    />
                    {form.errors.store_rating && <p className="mt-1 text-sm text-danger-600">{form.errors.store_rating}</p>}
                </div>
                {order.courier && (
                    <div>
                        <p className="mb-1 text-sm font-medium text-gray-700">Votre livreur, {order.courier.first_name}</p>
                        <StarRating
                            size="lg"
                            label="Note du livreur"
                            value={form.data.courier_rating}
                            onChange={(n) => form.setData('courier_rating', n)}
                        />
                        {form.errors.courier_rating && <p className="mt-1 text-sm text-danger-600">{form.errors.courier_rating}</p>}
                    </div>
                )}
                <Textarea
                    id="review-comment"
                    label="Un commentaire ? (facultatif)"
                    rows={3}
                    maxLength={500}
                    value={form.data.comment}
                    onChange={(e) => form.setData('comment', e.target.value)}
                    error={form.errors.comment}
                />
                <Button type="submit" loading={form.processing} className="w-full">
                    Envoyer mon avis
                </Button>
            </form>
        </Card>
    );
}
