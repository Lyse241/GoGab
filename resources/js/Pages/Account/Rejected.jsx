import AccountSubmission from '@/Components/AccountSubmission';
import Button from '@/Components/UI/Button';
import StatusBadge from '@/Components/UI/StatusBadge';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';
import { FilePen, XCircle } from 'lucide-react';

/**
 * Inscription refusée : motif + accès à la correction du dossier.
 */
export default function Rejected({ submission, rejection_reason: reason }) {
    return (
        <GuestLayout width="md">
            <Head title="Inscription refusée" />

            <div className="text-center">
                <span className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-danger-50 text-danger-600">
                    <XCircle className="h-8 w-8" aria-hidden="true" />
                </span>
                <StatusBadge type="account" status="rejected" className="mt-4" />
                <h1 className="mt-3 text-2xl font-bold text-secondary-900">Votre inscription n’a pas été validée</h1>
                <p className="mx-auto mt-2 max-w-md text-gray-600">
                    Corrigez les éléments indiqués puis renvoyez votre dossier : il sera vérifié à nouveau.
                </p>
            </div>

            <section className="mt-6 rounded-2xl border border-danger-200 bg-danger-50 p-4">
                <h2 className="text-sm font-semibold text-danger-800">Motif du refus</h2>
                <p className="mt-1 whitespace-pre-line text-sm text-danger-900">
                    {reason || 'Aucun motif n’a été précisé. Contactez l’équipe Gogab pour en savoir plus.'}
                </p>
            </section>

            <Button href={route('account.correction')} size="lg" fullWidth icon={FilePen} className="mt-5">
                Corriger et renvoyer
            </Button>

            <h2 className="mb-3 mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500">
                Ce que vous nous avez envoyé
            </h2>
            <AccountSubmission submission={submission} />
        </GuestLayout>
    );
}
