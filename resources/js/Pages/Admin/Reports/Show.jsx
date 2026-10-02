import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import Modal from '@/Components/UI/Modal';
import Select from '@/Components/UI/Select';
import StatusBadge from '@/Components/UI/StatusBadge';
import Textarea from '@/Components/UI/Textarea';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatFCFA } from '@/utils/format';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Ban, CheckCircle2, ExternalLink, Flag, History, Siren, TriangleAlert, XCircle } from 'lucide-react';
import { useId, useState } from 'react';

// Actions de traitement (ReportService::handle). Avertir et bloquer passent par ModerationService.
const ACTIONS = {
    warn: { label: 'Envoyer un avertissement', icon: TriangleAlert, variant: 'primary', fields: ['moderation_reason', 'message'], description: 'La personne signalée reçoit l’avertissement (sans savoir qui l’a signalée). Le signalement est marqué traité.' },
    block: { label: 'Bloquer le compte', icon: Ban, variant: 'danger', fields: ['moderation_reason', 'message', 'duration'], description: 'Effet immédiat. Le signalement est marqué traité.' },
    dismiss: { label: 'Classer sans suite', icon: XCircle, variant: 'outline', fields: [], description: 'Aucune sanction. Le signalant est prévenu que son signalement a été traité.' },
    resolve: { label: 'Marquer comme traité', icon: CheckCircle2, variant: 'outline', fields: [], description: 'Le problème est réglé (sans sanction depuis cette page). Le signalant est prévenu.' },
};

function Row({ label, children }) {
    return (
        <div className="flex justify-between gap-4 py-2 text-sm">
            <dt className="text-gray-600">{label}</dt>
            <dd className="min-w-0 break-words text-right font-medium text-gray-900">{children}</dd>
        </div>
    );
}

function HandleDialog({ action, report, moderationReasons, durations, onClose }) {
    const config = ACTIONS[action];
    const formId = useId();
    const { data, setData, post, processing, errors, transform } = useForm({
        action,
        moderation_reason: report.default_moderation_reason,
        message: '',
        duration: '7d',
        admin_note: '',
    });

    const submit = (event) => {
        event.preventDefault();
        transform((values) => Object.fromEntries(Object.entries(values).filter(([key]) => ['action', 'admin_note', ...config.fields].includes(key))));
        post(route('admin.reports.handle', report.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal
            open
            onClose={onClose}
            closeable={!processing}
            title={config.label}
            description={config.description}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={processing}>
                        Annuler
                    </Button>
                    <Button type="submit" form={formId} variant={config.variant === 'outline' ? 'primary' : config.variant} loading={processing}>
                        {config.label}
                    </Button>
                </>
            }
        >
            <form id={formId} onSubmit={submit} noValidate className="space-y-4">
                {(errors.report || errors.moderation) && (
                    <p role="alert" className="rounded-xl bg-danger-50 p-3 text-sm text-danger-800">
                        {errors.report ?? errors.moderation}
                    </p>
                )}
                {config.fields.includes('moderation_reason') && (
                    <Select id="moderation_reason" label="Motif de la sanction" options={moderationReasons} value={data.moderation_reason} onChange={(e) => setData('moderation_reason', e.target.value)} error={errors.moderation_reason} />
                )}
                {config.fields.includes('duration') && (
                    <Select id="duration" label="Durée du blocage" required options={durations} value={data.duration} onChange={(e) => setData('duration', e.target.value)} error={errors.duration} />
                )}
                {config.fields.includes('message') && (
                    <Textarea
                        id="message"
                        label="Message à la personne signalée"
                        required
                        rows={3}
                        maxLength={1000}
                        hint="Ne mentionnez pas qui a fait le signalement."
                        value={data.message}
                        onChange={(e) => setData('message', e.target.value)}
                        error={errors.message}
                    />
                )}
                <Textarea
                    id="admin_note"
                    label="Note admin (interne)"
                    rows={3}
                    maxLength={1000}
                    hint="Visible des administrateurs seulement."
                    value={data.admin_note}
                    onChange={(e) => setData('admin_note', e.target.value)}
                    error={errors.admin_note}
                />
            </form>
        </Modal>
    );
}

/**
 * Détail d'un signalement : faits, personne signalée et ses antécédents (autres signalements,
 * avertissements), commande concernée avec son historique, traitement.
 */
export default function Show({ report, reportedAccount, order, otherReports, warnings, moderationReasons, durations }) {
    const [action, setAction] = useState(null);
    const sanctions = reportedAccount.can_moderate;

    return (
        <DashboardLayout
            header={
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-bold text-secondary-900">Signalement #{report.id}</h1>
                    {report.is_urgent && (
                        <Badge color="danger" icon={Siren}>
                            Urgent
                        </Badge>
                    )}
                    <Badge color={report.status_color}>{report.status_label}</Badge>
                </div>
            }
            actions={
                <Button href={route('admin.reports.index')} variant="ghost" size="sm" icon={ArrowLeft}>
                    Signalements
                </Button>
            }
        >
            <Head title={`Signalement #${report.id}`} />

            <div className="mx-auto grid max-w-6xl gap-4 px-4 py-6 sm:px-6 lg:grid-cols-[1fr_22rem] lg:px-8">
                <div className="space-y-4">
                    <Card>
                        <CardHeader title={report.reason_label} description={`Le ${report.at}`} />
                        <dl className="divide-y divide-gray-100">
                            <Row label="Signalé par">
                                {report.reporter ? `${report.reporter.name} (${report.reporter.role_label})` : 'Compte supprimé'}
                            </Row>
                            <Row label="Personne signalée">
                                {report.reported.name} ({report.reported.role_label})
                            </Row>
                        </dl>
                        <p className="mt-3 whitespace-pre-line rounded-xl bg-gray-50 px-3 py-3 text-sm text-gray-800">{report.description}</p>
                        {!report.can_handle && (
                            <div className="mt-3 rounded-xl bg-gray-50 px-3 py-3 text-sm text-gray-700 ring-1 ring-gray-200">
                                <p>
                                    {report.status_label} par {report.handled_by ?? '—'} le {report.handled_at ?? '—'}.
                                </p>
                                {report.admin_note && (
                                    <p className="mt-1">
                                        <span className="font-semibold">Note admin :</span> {report.admin_note}
                                    </p>
                                )}
                            </div>
                        )}
                    </Card>

                    {report.can_handle && (
                        <Card>
                            <CardHeader title="Traitement" description="Le signalant sera prévenu que son signalement a été traité, sans détail de la sanction." />
                            {!sanctions && <p className="mb-3 rounded-xl bg-warning-50 px-3 py-2 text-sm text-warning-900">Ce compte ne peut pas être sanctionné depuis votre compte.</p>}
                            <div className="grid gap-2 sm:grid-cols-2">
                                {Object.entries(ACTIONS).map(([key, config]) => {
                                    const disabled = (key === 'warn' && !sanctions) || (key === 'block' && (!sanctions || !reportedAccount.can_block));

                                    return (
                                        <Button key={key} variant={config.variant} icon={config.icon} disabled={disabled} onClick={() => setAction(key)} className="justify-start">
                                            {config.label}
                                        </Button>
                                    );
                                })}
                            </div>
                            {sanctions && !reportedAccount.can_block && <p className="mt-2 text-xs text-gray-500">Blocage impossible : le compte n’est pas actif (déjà bloqué ou non validé).</p>}
                        </Card>
                    )}

                    {order && (
                        <Card>
                            <CardHeader
                                title={`Commande ${order.number}`}
                                description={`${order.store ?? '—'} · ${order.created_at}`}
                                action={<StatusBadge status={order.status} size="sm" />}
                            />
                            <dl className="divide-y divide-gray-100">
                                <Row label="Client">{order.client ?? '—'}</Row>
                                <Row label="Livreur">{order.courier ?? '—'}</Row>
                                <Row label="Total">{formatFCFA(order.total_price)}</Row>
                                {order.cancel_reason && <Row label="Motif d’arrêt">{order.cancel_reason}</Row>}
                            </dl>
                            <h3 className="mt-4 flex items-center gap-2 text-sm font-semibold text-secondary-900">
                                <History className="h-4 w-4 text-gray-400" aria-hidden="true" />
                                Historique des statuts
                            </h3>
                            <ol className="relative mt-3 space-y-3 border-l-2 border-gray-100 pl-5">
                                {order.history.map((entry) => (
                                    <li key={entry.id} className="relative">
                                        <span className="absolute -left-[1.6rem] top-1 h-3 w-3 rounded-full bg-primary-500 ring-4 ring-white" aria-hidden="true" />
                                        <p className="text-sm font-semibold text-gray-900">{entry.label}</p>
                                        <p className="text-xs text-gray-500">
                                            {entry.at}
                                            {entry.author && ` · ${entry.author}${entry.author_role ? ` (${entry.author_role})` : ''}`}
                                        </p>
                                        {entry.note && <p className="mt-1 rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700">{entry.note}</p>}
                                    </li>
                                ))}
                            </ol>
                        </Card>
                    )}
                </div>

                <div className="space-y-4">
                    <Card>
                        <CardHeader
                            title={reportedAccount.name}
                            description={reportedAccount.role_label}
                            action={reportedAccount.is_flagged && <Flag className="h-5 w-5 text-danger-600" aria-label="Compte signalé en interne" />}
                        />
                        <dl className="divide-y divide-gray-100">
                            <Row label="Compte">
                                <StatusBadge type="account" status={reportedAccount.status} size="sm" />
                            </Row>
                            <Row label="Téléphone">{reportedAccount.phone ?? '—'}</Row>
                            <Row label="E-mail">{reportedAccount.email}</Row>
                            <Row label="Avertissements">{reportedAccount.warnings_count}</Row>
                        </dl>
                        <Button href={route('admin.accounts.show', reportedAccount.id)} variant="outline" size="sm" iconRight={ExternalLink} fullWidth className="mt-3">
                            Fiche du compte
                        </Button>
                    </Card>

                    <Card>
                        <CardHeader title="Autres signalements" description={otherReports.length ? `${otherReports.length} sur ce compte` : 'Aucun autre signalement'} />
                        {otherReports.length > 0 && (
                            <ul className="-mx-1 divide-y divide-gray-100">
                                {otherReports.map((other) => (
                                    <li key={other.id}>
                                        <Link href={route('admin.reports.show', other.id)} className="block rounded-lg px-1 py-2 text-sm hover:bg-gray-50">
                                            <span className="flex flex-wrap items-center gap-1.5">
                                                {other.is_urgent && (
                                                    <Badge color="danger" size="sm">
                                                        Urgent
                                                    </Badge>
                                                )}
                                                <Badge color={other.status_color} size="sm">
                                                    {other.status_label}
                                                </Badge>
                                                <span className="font-medium text-gray-900">{other.reason_label}</span>
                                            </span>
                                            <span className="mt-0.5 block text-xs text-gray-500">
                                                {other.at} · par {other.reporter?.name ?? 'compte supprimé'}
                                                {other.order && ` · ${other.order.number}`}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card>
                        <CardHeader title="Avertissements" description={warnings.length ? undefined : 'Aucun avertissement'} />
                        {warnings.length > 0 && (
                            <ul className="divide-y divide-gray-100">
                                {warnings.map((warning) => (
                                    <li key={warning.id} className="py-2 text-sm">
                                        <p className="font-medium text-gray-900">{warning.reason_label}</p>
                                        <p className="text-xs text-gray-500">
                                            {warning.at} · par {warning.admin}
                                        </p>
                                        {warning.message && <p className="mt-1 rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700">{warning.message}</p>}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>

            {action && <HandleDialog action={action} report={report} moderationReasons={moderationReasons} durations={durations} onClose={() => setAction(null)} />}
        </DashboardLayout>
    );
}
