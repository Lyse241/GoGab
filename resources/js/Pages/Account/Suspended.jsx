import StatusBadge from '@/Components/UI/StatusBadge';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';
import { Ban, CalendarClock } from 'lucide-react';

/**
 * Compte bloqué par la modération : motif, message et date de fin éventuelle.
 * C'est la seule page accessible tant que le blocage dure (middleware RedirectIfBlocked).
 */
export default function Suspended({ submission, block, blockedUntil }) {
    return (
        <GuestLayout width="sm">
            <Head title="Compte suspendu" />

            <div className="text-center">
                <span className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-600">
                    <Ban className="h-8 w-8" aria-hidden="true" />
                </span>
                <StatusBadge type="account" status="suspended" className="mt-4" />
                <h1 className="mt-3 text-2xl font-bold text-secondary-900">Votre compte est suspendu</h1>
                <p className="mt-2 text-gray-600">
                    {submission.account.name}, votre compte a été bloqué par l’équipe Gogab. Vous ne pouvez plus commander,
                    livrer ni gérer de commerce pendant cette période.
                </p>
            </div>

            {block && (
                <section className="mt-6 rounded-2xl border border-danger-200 bg-danger-50 p-4 text-sm">
                    <h2 className="font-semibold text-danger-800">Motif : {block.reason}</h2>
                    {block.message && <p className="mt-1 whitespace-pre-line text-danger-900">{block.message}</p>}
                    <p className="mt-2 text-xs text-danger-700">Bloqué le {block.since}</p>
                </section>
            )}

            <p className="mt-4 flex items-start gap-2 rounded-2xl bg-gray-50 p-4 text-sm text-gray-700">
                <CalendarClock className="mt-0.5 h-5 w-5 shrink-0 text-gray-500" aria-hidden="true" />
                {blockedUntil ? (
                    <span>
                        Fin du blocage : <strong className="text-gray-900">{blockedUntil}</strong>. Votre compte sera réactivé
                        automatiquement et vous recevrez une notification.
                    </span>
                ) : (
                    <span>Blocage jusqu’à nouvel ordre. Pensez à une erreur ? Contactez l’équipe Gogab en indiquant l’adresse {submission.account.email}.</span>
                )}
            </p>
        </GuestLayout>
    );
}
