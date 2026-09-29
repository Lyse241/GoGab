import AccountActivity from '@/Components/Admin/AccountActivity';
import ModerationPanel from '@/Components/Admin/ModerationPanel';
import DocumentPreview from '@/Components/DocumentPreview';
import { DAYS } from '@/Components/OpeningHoursEditor';
import { Avatar } from '@/Components/Layout/UserMenu';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import Modal from '@/Components/UI/Modal';
import StatusBadge from '@/Components/UI/StatusBadge';
import Textarea from '@/Components/UI/Textarea';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { TYPE_BADGES } from '@/Pages/Admin/Accounts/Index';
import { imageUrl } from '@/utils/format';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Bike, Check, CircleAlert, Clock, FileQuestion, Flag, History, Store, UserRound, X } from 'lucide-react';
import { useId, useState } from 'react';

function Row({ label, value }) {
    return (
        <div className="flex flex-col gap-0.5 py-2 sm:flex-row sm:justify-between sm:gap-6">
            <dt className="text-sm text-gray-500">{label}</dt>
            <dd className="text-sm font-medium text-gray-900 sm:text-right">{value || <span className="text-gray-400">—</span>}</dd>
        </div>
    );
}

/**
 * Motif obligatoire avant un refus (document ou compte).
 */
function RejectDialog({ open, onClose, url, title, description, confirmLabel }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ reason: '' });
    // Identifiants propres à chaque fenêtre : deux fenêtres peuvent coexister pendant une transition.
    const formId = useId();

    const close = () => {
        reset();
        clearErrors();
        onClose();
    };

    const submit = (event) => {
        event.preventDefault();
        post(url, { preserveScroll: true, onSuccess: close });
    };

    return (
        <Modal
            open={open}
            onClose={close}
            closeable={!processing}
            title={title}
            description={description}
            footer={
                <>
                    <Button variant="outline" onClick={close} disabled={processing}>
                        Annuler
                    </Button>
                    <Button type="submit" form={formId} variant="danger" loading={processing}>
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            <form id={formId} onSubmit={submit} noValidate>
                <Textarea
                    id={`${formId}-reason`}
                    label="Motif du refus"
                    hint="Il sera montré à l’utilisateur : soyez précis sur ce qu’il doit corriger."
                    placeholder="Ex. : la photo de la CIN est floue, le numéro est illisible."
                    rows={4}
                    maxLength={1000}
                    value={data.reason}
                    onChange={(event) => setData('reason', event.target.value)}
                    error={errors.reason}
                    required
                />
            </form>
        </Modal>
    );
}

function DocumentCard({ document, onReject }) {
    const [approving, setApproving] = useState(false);

    if (document.missing) {
        return (
            <li className="flex min-h-40 flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-danger-200 bg-danger-50/40 p-4 text-center">
                <FileQuestion className="h-8 w-8 text-danger-400" aria-hidden="true" />
                <p className="font-semibold text-gray-900">{document.label}</p>
                <Badge color="danger" size="sm">Non fourni</Badge>
            </li>
        );
    }

    const approve = () =>
        router.post(route('admin.documents.approve', document.id), {}, {
            preserveScroll: true,
            onStart: () => setApproving(true),
            onFinish: () => setApproving(false),
        });

    return (
        <li className="flex flex-col rounded-2xl bg-white p-3 shadow-card ring-1 ring-gray-100">
            <DocumentPreview document={document} />
            <div className="mt-3 flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="font-semibold text-gray-900">{document.label}</p>
                    <p className="truncate text-xs text-gray-500">
                        {document.original_name} · envoyé le {document.sent_at}
                    </p>
                </div>
                {!document.required && <Badge size="sm">Facultatif</Badge>}
            </div>
            <div className="mt-2 flex flex-wrap items-center gap-2">
                <Badge color={document.status_color} size="sm" dot>
                    {document.status_label}
                </Badge>
                {document.reviewed_by && (
                    <span className="text-xs text-gray-500">
                        par {document.reviewed_by}, le {document.reviewed_at}
                    </span>
                )}
            </div>
            {document.rejection_reason && (
                <p className="mt-2 rounded-lg bg-danger-50 px-2.5 py-1.5 text-xs text-danger-800">{document.rejection_reason}</p>
            )}
            <div className="mt-auto grid grid-cols-2 gap-2 pt-3">
                <Button size="sm" variant="outline" icon={X} onClick={() => onReject(document)} disabled={document.status === 'rejected'}>
                    Refuser
                </Button>
                <Button size="sm" icon={Check} onClick={approve} loading={approving} disabled={document.status === 'approved'}>
                    Approuver
                </Button>
            </div>
        </li>
    );
}

export default function Show({ account, deliveryProfile, store, documents, decision, moderation, history, activity }) {
    const [rejectingDocument, setRejectingDocument] = useState(null);
    const [rejectingAccount, setRejectingAccount] = useState(false);
    const [approving, setApproving] = useState(false);
    const { errors } = usePage().props;
    const type = TYPE_BADGES[account.role];
    const status = account.account_status;
    const reviewed = documents.filter((document) => document.required && document.status === 'approved').length;
    const requiredCount = documents.filter((document) => document.required).length;

    const approveAccount = () =>
        router.post(route('admin.accounts.approve', account.id), {}, {
            preserveScroll: true,
            onStart: () => setApproving(true),
            onFinish: () => setApproving(false),
        });

    return (
        <DashboardLayout
            header={
                <div className="flex items-center gap-3">
                    <Avatar name={account.name} initials={account.initials} className="h-12 w-12 text-base" />
                    <div className="min-w-0">
                        <h1 className="truncate text-xl font-bold text-secondary-900">{account.name}</h1>
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            {type && (
                                <Badge color={type.color} icon={type.icon} size="sm">
                                    {account.role_label}
                                </Badge>
                            )}
                            <StatusBadge type="account" status={status} size="sm" />
                            {moderation.is_flagged && (
                                <Badge color="purple" size="sm" icon={Flag}>
                                    Signalé
                                </Badge>
                            )}
                        </div>
                    </div>
                </div>
            }
            actions={
                <Button href={route('admin.accounts.index')} variant="ghost" size="sm" icon={ArrowLeft}>
                    Tous les comptes
                </Button>
            }
        >
            <Head title={`Compte · ${account.name}`} />

            <div className="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[1fr_22rem] lg:px-8">
                <div className="space-y-6">
                    {/* Informations */}
                    <div className="grid gap-4 xl:grid-cols-2">
                        <Card>
                            <CardHeader title="Informations personnelles" action={<UserRound className="h-5 w-5 text-primary-600" aria-hidden="true" />} />
                            <dl className="divide-y divide-gray-100">
                                <Row label="E-mail" value={account.email} />
                                <Row label="Téléphone" value={account.phone} />
                                <Row label="Quartier" value={account.neighborhood && `${account.neighborhood} (zone ${account.zone})`} />
                                {account.role !== 'business' && <Row label="Adresse et repères" value={account.address_landmarks} />}
                                <Row label="Inscription" value={account.registered_at} />
                                {account.approved_at && <Row label="Validé" value={`le ${account.approved_at}${account.approved_by ? ` par ${account.approved_by}` : ''}`} />}
                            </dl>
                        </Card>

                        {deliveryProfile && (
                            <Card>
                                <CardHeader title="Véhicule" action={<Bike className="h-5 w-5 text-primary-600" aria-hidden="true" />} />
                                <dl className="divide-y divide-gray-100">
                                    <Row label="Type" value={deliveryProfile.vehicle} />
                                    <Row label="Marque et modèle" value={deliveryProfile.vehicle_brand} />
                                    {deliveryProfile.vehicle_type !== 'bicycle' && (
                                        <>
                                            <Row label="Plaque" value={deliveryProfile.plate_number} />
                                            <Row label="Permis" value={deliveryProfile.license_number} />
                                        </>
                                    )}
                                    <Row label="Zone d’activité" value={deliveryProfile.base_neighborhood && `${deliveryProfile.base_neighborhood} (zone ${deliveryProfile.base_zone})`} />
                                    <Row label="Disponibilité" value={activity.is_available ? 'Disponible pour des courses' : 'Indisponible'} />
                                </dl>
                            </Card>
                        )}

                        {store && (
                            <Card className="xl:col-span-2">
                                <CardHeader
                                    title="Fiche du commerce"
                                    description={store.is_active ? 'Visible par les clients' : 'Invisible tant que le compte n’est pas validé'}
                                    action={<Store className="h-5 w-5 text-primary-600" aria-hidden="true" />}
                                />
                                <div className="grid gap-6 md:grid-cols-2">
                                    <div>
                                        {store.logo && (
                                            <img src={imageUrl(store.logo)} alt={`Logo de ${store.name}`} className="mb-3 h-16 w-16 rounded-xl object-cover ring-1 ring-gray-200" />
                                        )}
                                        <dl className="divide-y divide-gray-100">
                                            <Row label="Nom commercial" value={store.name} />
                                            <Row label="Catégorie" value={store.category} />
                                            <Row label="Téléphone" value={store.phone} />
                                            <Row label="Quartier" value={store.neighborhood && `${store.neighborhood} (zone ${store.zone})`} />
                                            <Row label="Adresse et repères" value={store.address_landmarks} />
                                        </dl>
                                        {store.description && <p className="mt-3 rounded-xl bg-gray-50 p-3 text-sm text-gray-700">{store.description}</p>}
                                    </div>
                                    <div>
                                        <p className="flex items-center gap-2 text-sm font-semibold text-gray-800">
                                            <Clock className="h-4 w-4 text-primary-600" aria-hidden="true" />
                                            Horaires
                                        </p>
                                        <ul className="mt-2 space-y-1 text-sm">
                                            {store.opening_hours.map((day, index) => (
                                                <li key={day.day_of_week} className="flex justify-between rounded-lg px-2 py-1 odd:bg-gray-50">
                                                    <span className="text-gray-600">{DAYS[index]}</span>
                                                    <span className="font-medium text-gray-900">
                                                        {day.is_closed || !day.opens_at
                                                            ? 'Fermé'
                                                            : day.opens_at === day.closes_at
                                                              ? '24 h/24'
                                                              : `${day.opens_at.replace(':', 'h')} – ${day.closes_at.replace(':', 'h')}`}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>
                            </Card>
                        )}
                    </div>

                    <AccountActivity role={account.role} activity={activity} />

                    {/* Documents */}
                    <section aria-labelledby="documents-title">
                        <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                            <h2 id="documents-title" className="text-lg font-bold text-secondary-900">Documents</h2>
                            {requiredCount > 0 && (
                                <p className="text-sm text-gray-600">
                                    {reviewed} / {requiredCount} documents obligatoires approuvés
                                </p>
                            )}
                        </div>
                        {documents.length === 0 ? (
                            <p className="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">
                                Aucun document n’est demandé pour ce type de compte.
                            </p>
                        ) : (
                            <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {documents.map((document) => (
                                    <DocumentCard key={document.id ?? document.type} document={document} onReject={setRejectingDocument} />
                                ))}
                            </ul>
                        )}
                    </section>
                </div>

                {/* Décision + historique */}
                <aside className="space-y-6 lg:sticky lg:top-20 lg:self-start">
                    <Card>
                        <CardHeader title="Décision" />
                        {status === 'suspended' ? (
                            <p className="flex items-start gap-2 rounded-xl bg-gray-100 p-3 text-sm text-gray-700">
                                <CircleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                Compte bloqué : sa situation se gère dans la modération ci-dessous.
                            </p>
                        ) : status === 'approved' ? (
                            <p className="flex items-start gap-2 rounded-xl bg-primary-50 p-3 text-sm text-primary-800">
                                <Check className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                Compte validé le {account.approved_at}
                                {account.approved_by && ` par ${account.approved_by}`}.
                            </p>
                        ) : (
                            <>
                                {status === 'rejected' && account.rejection_reason && (
                                    <div className="mb-3 rounded-xl bg-danger-50 p-3 text-sm text-danger-800">
                                        <p className="font-semibold">Refusé · en attente de correction</p>
                                        <p className="mt-1">{account.rejection_reason}</p>
                                    </div>
                                )}
                                {!decision.can_approve && decision.missing.length > 0 && (
                                    <div role="status" className="mb-3 flex items-start gap-2 rounded-xl bg-accent-50 p-3 text-sm text-secondary-900 ring-1 ring-accent-200">
                                        <CircleAlert className="mt-0.5 h-4 w-4 shrink-0 text-accent-700" aria-hidden="true" />
                                        <p>
                                            Pour valider, approuvez d’abord : {decision.missing.join(', ')}.
                                        </p>
                                    </div>
                                )}
                                {errors.account && (
                                    <p role="alert" className="mb-3 rounded-xl bg-danger-50 p-3 text-sm text-danger-800">
                                        {errors.account}
                                    </p>
                                )}
                                <div className="grid gap-2">
                                    <Button icon={Check} fullWidth onClick={approveAccount} loading={approving} disabled={!decision.can_approve}>
                                        Approuver le compte
                                    </Button>
                                    <Button variant="outline" icon={X} fullWidth onClick={() => setRejectingAccount(true)}>
                                        Refuser l’inscription
                                    </Button>
                                </div>
                                <p className="mt-3 text-xs text-gray-500">
                                    {account.role === 'business'
                                        ? 'La validation met le commerce en ligne et prévient le gérant.'
                                        : 'L’utilisateur reçoit une notification dans les deux cas.'}
                                </p>
                            </>
                        )}
                    </Card>

                    <ModerationPanel account={account} moderation={moderation} />

                    <Card>
                        <CardHeader title="Historique du dossier" action={<History className="h-5 w-5 text-gray-400" aria-hidden="true" />} />
                        {history.length === 0 ? (
                            <p className="text-sm text-gray-500">Aucun événement.</p>
                        ) : (
                            <ol className="relative space-y-4 border-l-2 border-gray-100 pl-5">
                                {history.map((event) => (
                                    <li key={event.id} className="relative">
                                        <span
                                            className={`absolute -left-[1.6rem] top-1 h-3 w-3 rounded-full ring-4 ring-white ${
                                                event.color === 'green' ? 'bg-primary-500' : event.color === 'red' ? 'bg-danger-500' : 'bg-secondary-400'
                                            }`}
                                            aria-hidden="true"
                                        />
                                        <p className="text-sm font-semibold text-gray-900">
                                            {event.label}
                                            {event.document && <span className="font-normal text-gray-600"> · {event.document}</span>}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            <time dateTime={event.at_iso}>{event.at}</time>
                                            {event.actor && ` · ${event.by_owner ? 'par l’utilisateur' : event.actor}`}
                                        </p>
                                        {event.note && <p className="mt-1 rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700">{event.note}</p>}
                                    </li>
                                ))}
                            </ol>
                        )}
                    </Card>
                </aside>
            </div>

            <RejectDialog
                open={rejectingDocument !== null}
                onClose={() => setRejectingDocument(null)}
                url={rejectingDocument ? route('admin.documents.reject', rejectingDocument.id) : ''}
                title={`Refuser : ${rejectingDocument?.label ?? ''}`}
                description="L’utilisateur devra renvoyer ce document."
                confirmLabel="Refuser le document"
            />
            <RejectDialog
                open={rejectingAccount}
                onClose={() => setRejectingAccount(false)}
                url={route('admin.accounts.reject', account.id)}
                title="Refuser l’inscription"
                description={`${account.name} recevra ce motif et pourra corriger puis renvoyer son dossier.`}
                confirmLabel="Refuser l’inscription"
            />
        </DashboardLayout>
    );
}
