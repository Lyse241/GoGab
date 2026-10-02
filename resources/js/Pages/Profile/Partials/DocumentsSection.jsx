import DocumentUploader from '@/Components/DocumentUploader';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import Modal from '@/Components/UI/Modal';
import { Link, useForm } from '@inertiajs/react';
import { ExternalLink, RefreshCw, TriangleAlert, Upload } from 'lucide-react';
import { useState } from 'react';

function ReplaceDialog({ document, accountApproved, onClose }) {
    const { data, setData, post, processing, errors, progress } = useForm({ file: null });
    const revalidation = accountApproved && document.triggers_revalidation;

    const submit = (event) => {
        event.preventDefault();
        post(route('profile.documents.replace', document.type), { preserveScroll: true, forceFormData: true, onSuccess: onClose });
    };

    return (
        <Modal
            open
            onClose={onClose}
            closeable={!processing}
            title={document.status ? `Remplacer : ${document.label}` : `Envoyer : ${document.label}`}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={processing}>
                        Annuler
                    </Button>
                    <Button type="submit" form="replace-document" icon={Upload} loading={processing} disabled={!data.file}>
                        Envoyer
                    </Button>
                </>
            }
        >
            <form id="replace-document" onSubmit={submit} noValidate className="space-y-3">
                {revalidation && (
                    <p className="flex items-start gap-2 rounded-xl bg-warning-50 px-3 py-2 text-sm text-warning-900 ring-1 ring-warning-200">
                        <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                        Document obligatoire : votre compte repassera en validation. Vous ne pourrez plus recevoir de commandes ni de courses jusqu’à la décision de l’équipe Gogab.
                    </p>
                )}
                {errors.document && (
                    <p role="alert" className="rounded-xl bg-danger-50 px-3 py-2 text-sm text-danger-800">
                        {errors.document}
                    </p>
                )}
                <DocumentUploader spec={document.uploader} value={data.file} onChange={(file) => setData('file', file)} error={errors.file} />
                {progress && <p className="text-xs text-gray-500">Envoi… {progress.percentage} %</p>}
            </form>
        </Modal>
    );
}

/**
 * Documents du livreur ou de l'entreprise : statut de chacun, motif de refus, renvoi ou
 * remplacement. Un document obligatoire remplacé sur un compte validé le renvoie en validation.
 */
export default function DocumentsSection({ documents }) {
    const [replacing, setReplacing] = useState(null);

    return (
        <Card>
            <CardHeader title="Mes documents" description="Renvoyez un document refusé ou remplacez-en un qui a expiré." />

            {!documents.can_replace && (
                <p className="mb-3 rounded-xl bg-danger-50 px-3 py-2 text-sm text-danger-800">
                    Votre inscription a été refusée : corrigez votre dossier depuis{' '}
                    <Link href={route('account.correction')} className="font-semibold underline">
                        la page dédiée
                    </Link>
                    .
                </p>
            )}

            <ul className="divide-y divide-gray-100">
                {documents.items.map((document) => (
                    <li key={document.type} className="py-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="min-w-0">
                                <p className="text-sm font-medium text-gray-900">{document.label}</p>
                                <p className="text-xs text-gray-500">{document.required ? 'Obligatoire' : 'Facultatif'}</p>
                            </div>
                            <div className="flex shrink-0 items-center gap-1">
                                {document.status ? (
                                    <Badge color={document.status_color} size="sm">
                                        {document.status_label}
                                    </Badge>
                                ) : (
                                    <Badge color={document.required ? 'danger' : 'neutral'} size="sm">
                                        {document.required ? 'Manquant' : 'Non envoyé'}
                                    </Badge>
                                )}
                                {document.url && (
                                    <a
                                        href={document.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex h-tap w-tap items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-secondary"
                                        aria-label={`Voir ${document.label}`}
                                    >
                                        <ExternalLink className="h-4 w-4" aria-hidden="true" />
                                    </a>
                                )}
                                {documents.can_replace && (
                                    <Button
                                        size="sm"
                                        variant={document.status === 'rejected' || !document.status ? 'primary' : 'ghost'}
                                        icon={document.status ? RefreshCw : Upload}
                                        onClick={() => setReplacing(document)}
                                    >
                                        {document.status === 'rejected' ? 'Renvoyer' : document.status ? 'Remplacer' : 'Envoyer'}
                                    </Button>
                                )}
                            </div>
                        </div>
                        {document.status === 'rejected' && document.rejection_reason && (
                            <p className="mt-1.5 rounded-lg bg-danger-50 px-2.5 py-1.5 text-xs text-danger-700">Motif du refus : {document.rejection_reason}</p>
                        )}
                    </li>
                ))}
            </ul>

            {replacing && <ReplaceDialog document={replacing} accountApproved={documents.account_approved} onClose={() => setReplacing(null)} />}
        </Card>
    );
}
