import AccountSubmission from '@/Components/AccountSubmission';
import Button from '@/Components/UI/Button';
import StatusBadge from '@/Components/UI/StatusBadge';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';
import { Bell, CircleCheck, FileSearch, Hourglass, Store } from 'lucide-react';

/**
 * Compte en attente de validation par un administrateur.
 * `justRegistered` : écran de confirmation juste après l'envoi du dossier.
 */
export default function Pending({ submission, justRegistered = false }) {
    const isClient = submission.account.role === 'client';
    const documentCount = submission.documents.length;

    return (
        <GuestLayout width="md">
            <Head title={justRegistered ? 'Dossier envoyé' : 'Compte en cours de validation'} />

            <div className="text-center">
                <span
                    className={`mx-auto flex h-16 w-16 items-center justify-center rounded-full ${
                        justRegistered ? 'bg-primary-50 text-primary-600' : 'bg-accent-100 text-accent-700'
                    }`}
                >
                    {justRegistered ? (
                        <CircleCheck className="h-8 w-8" aria-hidden="true" />
                    ) : (
                        <Hourglass className="h-8 w-8" aria-hidden="true" />
                    )}
                </span>
                <StatusBadge type="account" status="pending" className="mt-4" />
                <h1 className="mt-3 text-2xl font-bold text-secondary-900">
                    {justRegistered
                        ? documentCount > 0
                            ? 'Merci, votre dossier est bien envoyé !'
                            : 'Merci, votre compte est bien créé !'
                        : 'Votre compte est en cours de validation'}
                </h1>
                <p className="mx-auto mt-2 max-w-md text-gray-600">
                    {justRegistered
                        ? 'Nous examinons votre dossier, vous serez notifié dès la validation.'
                        : 'L’équipe Gogab vérifie vos informations. Vous recevrez une notification dès que votre compte sera validé.'}
                    {isClient && ' En attendant, vous pouvez parcourir les commerces et préparer votre panier.'}
                </p>
                {isClient && (
                    <Button href={route('home')} className="mt-5" icon={Store}>
                        Parcourir les commerces
                    </Button>
                )}
            </div>

            {justRegistered && (
                <ol className="mt-6 space-y-3 rounded-2xl bg-gray-50 p-4 text-sm">
                    <li className="flex items-start gap-3">
                        <CircleCheck className="mt-0.5 h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" />
                        <span>
                            <span className="font-semibold text-gray-900">Dossier reçu</span>
                            {documentCount > 0 && ` · ${documentCount} document${documentCount > 1 ? 's' : ''} envoyé${documentCount > 1 ? 's' : ''}`}
                        </span>
                    </li>
                    <li className="flex items-start gap-3">
                        <FileSearch className="mt-0.5 h-5 w-5 shrink-0 text-accent-600" aria-hidden="true" />
                        <span>
                            <span className="font-semibold text-gray-900">Vérification par l’équipe Gogab</span>
                        </span>
                    </li>
                    <li className="flex items-start gap-3">
                        <Bell className="mt-0.5 h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" />
                        <span>
                            <span className="font-semibold text-gray-900">Notification de validation</span>
                            {' · vous pourrez alors '}
                            {isClient ? 'commander' : submission.account.role === 'delivery' ? 'accepter vos premières courses' : 'recevoir des commandes'}
                        </span>
                    </li>
                </ol>
            )}

            <h2 className="mb-3 mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500">
                Ce que vous nous avez envoyé
            </h2>
            <AccountSubmission submission={submission} />
        </GuestLayout>
    );
}
