import Badge from '@/Components/UI/Badge';
import { Bike, FileText, Store, UserRound } from 'lucide-react';

const documentColors = { pending: 'yellow', approved: 'green', rejected: 'red' };

function Row({ label, value }) {
    if (!value) {
        return null;
    }

    return (
        <div className="flex flex-col gap-0.5 py-2 sm:flex-row sm:justify-between sm:gap-4">
            <dt className="text-sm text-gray-500">{label}</dt>
            <dd className="text-sm font-medium text-gray-900 sm:text-right">{value}</dd>
        </div>
    );
}

function Block({ icon: Icon, title, children }) {
    return (
        <section className="rounded-2xl border border-gray-200 p-4">
            <h3 className="flex items-center gap-2 font-semibold text-secondary-900">
                <Icon className="h-5 w-5 text-primary-600" aria-hidden="true" />
                {title}
            </h3>
            <dl className="mt-2 divide-y divide-gray-100">{children}</dl>
        </section>
    );
}

/**
 * Récapitulatif de ce que l'utilisateur a envoyé à l'inscription
 * (données préparées par AccountStatusController).
 */
export default function AccountSubmission({ submission }) {
    const { account, delivery_profile: profile, store, documents } = submission;

    return (
        <div className="space-y-3">
            <Block icon={UserRound} title="Votre compte">
                <Row label="Profil" value={account.role_label} />
                <Row label="Nom" value={account.name} />
                <Row label="E-mail" value={account.email} />
                <Row label="Téléphone" value={account.phone} />
                <Row label="Quartier" value={account.neighborhood} />
                <Row label="Inscription" value={account.registered_at ? `le ${account.registered_at}` : null} />
            </Block>

            {profile && (
                <Block icon={Bike} title="Votre véhicule">
                    <Row label="Type" value={profile.vehicle} />
                    <Row label="Marque" value={profile.vehicle_brand} />
                    <Row label="Plaque" value={profile.plate_number} />
                    <Row label="Permis" value={profile.license_number} />
                    <Row label="Quartier de rattachement" value={profile.base_neighborhood} />
                </Block>
            )}

            {store && (
                <Block icon={Store} title="Votre commerce">
                    <Row label="Nom" value={store.name} />
                    <Row label="Catégorie" value={store.category} />
                    <Row label="Quartier" value={store.neighborhood} />
                    <Row label="Téléphone" value={store.phone} />
                </Block>
            )}

            {documents.length > 0 && (
                <section className="rounded-2xl border border-gray-200 p-4">
                    <h3 className="flex items-center gap-2 font-semibold text-secondary-900">
                        <FileText className="h-5 w-5 text-primary-600" aria-hidden="true" />
                        Documents envoyés ({documents.length})
                    </h3>
                    <ul className="mt-2 divide-y divide-gray-100">
                        {documents.map((document) => (
                            <li key={document.id} className="py-2.5">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-gray-900">{document.type}</p>
                                        <p className="truncate text-xs text-gray-500">
                                            {document.original_name}
                                            {document.sent_at && ` · envoyé le ${document.sent_at}`}
                                        </p>
                                    </div>
                                    <Badge color={documentColors[document.status] ?? 'gray'} size="sm" dot>
                                        {document.status_label}
                                    </Badge>
                                </div>
                                {document.rejection_reason && (
                                    <p className="mt-1.5 rounded-lg bg-danger-50 px-2.5 py-1.5 text-xs text-danger-800">
                                        {document.rejection_reason}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}
