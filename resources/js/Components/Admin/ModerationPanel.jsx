import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import Modal from '@/Components/UI/Modal';
import Select from '@/Components/UI/Select';
import StatusBadge from '@/Components/UI/StatusBadge';
import Textarea from '@/Components/UI/Textarea';
import { cn } from '@/utils/cn';
import { formatFCFA } from '@/utils/format';
import { useForm, usePage } from '@inertiajs/react';
import { Ban, Flag, FlagOff, LockOpen, ShieldAlert, TriangleAlert } from 'lucide-react';
import { useId, useState } from 'react';

// Champs et libellés de chaque action (les règles sont vérifiées par le serveur).
const ACTIONS = {
    warn: {
        title: 'Envoyer un avertissement',
        description: 'L’utilisateur est notifié et devra accuser réception à sa prochaine connexion.',
        confirm: 'Envoyer l’avertissement',
        variant: 'primary',
        fields: ['reason', 'message'],
        messageLabel: 'Message à l’utilisateur',
    },
    block: {
        title: 'Bloquer le compte',
        description: 'Effet immédiat : l’utilisateur ne voit plus que la page « compte suspendu ».',
        confirm: 'Bloquer le compte',
        variant: 'danger',
        fields: ['reason', 'message', 'duration'],
        messageLabel: 'Message à l’utilisateur',
    },
    unblock: {
        title: 'Débloquer le compte',
        description: 'L’utilisateur retrouve l’accès à Gogab et en est notifié.',
        confirm: 'Débloquer',
        variant: 'primary',
        fields: ['reason', 'message'],
        messageLabel: 'Message à l’utilisateur (facultatif)',
        messageOptional: true,
    },
    flag: {
        title: 'Signaler ce compte',
        description: 'Signalement interne : jamais visible ni notifié à l’utilisateur.',
        confirm: 'Signaler',
        variant: 'primary',
        fields: ['reason', 'internal_note'],
    },
    unflag: {
        title: 'Retirer le signalement',
        description: 'Le drapeau disparaît des listes et de la fiche.',
        confirm: 'Retirer le signalement',
        variant: 'primary',
        fields: ['internal_note'],
        noteOptional: true,
    },
};

function ModerationDialog({ action, accountId, moderation, onClose }) {
    const config = ACTIONS[action];
    const formId = useId();
    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm({
        reason: '',
        message: '',
        duration: '7d',
        internal_note: '',
    });

    const close = () => {
        reset();
        clearErrors();
        onClose();
    };

    const submit = (event) => {
        event.preventDefault();
        // Seuls les champs de l'action sont envoyés.
        transform((values) => Object.fromEntries(config.fields.map((field) => [field, values[field]])));
        post(route(`admin.accounts.moderation.${action}`, accountId), { preserveScroll: true, onSuccess: close });
    };

    return (
        <Modal
            open
            onClose={close}
            closeable={!processing}
            title={config.title}
            description={config.description}
            footer={
                <>
                    <Button variant="outline" onClick={close} disabled={processing}>
                        Annuler
                    </Button>
                    <Button type="submit" form={formId} variant={config.variant} loading={processing}>
                        {config.confirm}
                    </Button>
                </>
            }
        >
            <form id={formId} onSubmit={submit} noValidate className="space-y-4">
                {errors.moderation && (
                    <p role="alert" className="rounded-xl bg-danger-50 p-3 text-sm text-danger-800">
                        {errors.moderation}
                    </p>
                )}
                {action === 'block' && moderation.active_orders.length > 0 && (
                    <div role="status" className="rounded-xl bg-warning-50 p-3 text-sm ring-1 ring-warning-200">
                        <p className="font-semibold text-warning-900">
                            {moderation.active_orders.length} commande{moderation.active_orders.length > 1 ? 's' : ''} en cours
                        </p>
                        <p className="mt-0.5 text-warning-800">Elles restent visibles de l’administration pour être annulées ou relancées.</p>
                        <ul className="mt-2 space-y-1">
                            {moderation.active_orders.map((order) => (
                                <li key={order.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-2.5 py-1.5">
                                    <span className="font-medium text-gray-900">
                                        {order.reference} · {order.store}
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <span className="text-gray-600">{formatFCFA(order.total_price)}</span>
                                        <StatusBadge status={order.status} size="sm" />
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {config.fields.includes('reason') && (
                    <Select
                        id={`${formId}-reason`}
                        label="Motif"
                        placeholder="Choisissez un motif"
                        options={moderation.reasons}
                        value={data.reason}
                        onChange={(event) => setData('reason', event.target.value)}
                        error={errors.reason}
                        required
                    />
                )}

                {config.fields.includes('duration') && (
                    <fieldset>
                        <legend className="mb-1.5 text-sm font-medium text-gray-800">Durée</legend>
                        <div className="grid grid-cols-2 gap-2">
                            {moderation.durations.map((duration) => (
                                <label
                                    key={duration.value}
                                    className={cn(
                                        'flex min-h-tap cursor-pointer items-center justify-center rounded-xl px-3 text-center text-sm font-semibold ring-1 transition focus-within:ring-2 focus-within:ring-primary',
                                        data.duration === duration.value ? 'bg-danger-50 text-danger-800 ring-danger-300' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name={`${formId}-duration`}
                                        value={duration.value}
                                        checked={data.duration === duration.value}
                                        onChange={() => setData('duration', duration.value)}
                                        className="sr-only"
                                    />
                                    {duration.label}
                                </label>
                            ))}
                        </div>
                        {errors.duration && <p className="mt-1.5 text-sm text-danger-700">{errors.duration}</p>}
                    </fieldset>
                )}

                {config.fields.includes('message') && (
                    <Textarea
                        id={`${formId}-message`}
                        label={config.messageLabel}
                        hint="Visible par l’utilisateur."
                        rows={3}
                        maxLength={1000}
                        value={data.message}
                        onChange={(event) => setData('message', event.target.value)}
                        error={errors.message}
                        required={!config.messageOptional}
                    />
                )}

                {config.fields.includes('internal_note') && (
                    <Textarea
                        id={`${formId}-note`}
                        label={config.noteOptional ? 'Note interne (facultatif)' : 'Note interne'}
                        hint="Visible des administrateurs seulement."
                        rows={3}
                        maxLength={1000}
                        value={data.internal_note}
                        onChange={(event) => setData('internal_note', event.target.value)}
                        error={errors.internal_note}
                        required={!config.noteOptional}
                    />
                )}
            </form>
        </Modal>
    );
}

/**
 * Modération d'un compte (fiche admin) : état, actions et historique.
 */
export default function ModerationPanel({ account, moderation }) {
    const [action, setAction] = useState(null);
    const { errors } = usePage().props;
    const status = account.account_status;

    return (
        <>
            <Card>
                <CardHeader title="Modération" action={<ShieldAlert className="h-5 w-5 text-gray-400" aria-hidden="true" />} />

                <div className="flex flex-wrap gap-2">
                    <Badge color={moderation.warnings_count > 0 ? 'warning' : 'neutral'} size="sm">
                        {moderation.warnings_count} avertissement{moderation.warnings_count > 1 ? 's' : ''}
                    </Badge>
                    {moderation.is_blocked && (
                        <Badge color="danger" size="sm" icon={Ban}>
                            {moderation.blocked_until ? `Bloqué jusqu’au ${moderation.blocked_until}` : 'Bloqué jusqu’à nouvel ordre'}
                        </Badge>
                    )}
                    {moderation.is_flagged && (
                        <Badge color="purple" size="sm" icon={Flag}>
                            Signalé
                        </Badge>
                    )}
                </div>

                {moderation.suggest_block && (
                    <p role="status" className="mt-3 flex items-start gap-2 rounded-xl bg-danger-50 p-3 text-sm text-danger-800">
                        <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                        {moderation.warnings_count} avertissements : envisagez un blocage du compte.
                    </p>
                )}

                {errors.moderation && (
                    <p role="alert" className="mt-3 rounded-xl bg-danger-50 p-3 text-sm text-danger-800">
                        {errors.moderation}
                    </p>
                )}

                {moderation.can_moderate && (
                    <div className="mt-4 grid gap-2">
                        <Button variant="outline" icon={TriangleAlert} fullWidth onClick={() => setAction('warn')}>
                            Envoyer un avertissement
                        </Button>
                        {moderation.is_blocked ? (
                            <Button icon={LockOpen} fullWidth onClick={() => setAction('unblock')}>
                                Débloquer
                            </Button>
                        ) : (
                            status === 'approved' && (
                                <Button variant="danger" icon={Ban} fullWidth onClick={() => setAction('block')}>
                                    Bloquer le compte
                                </Button>
                            )
                        )}
                        {moderation.is_flagged ? (
                            <Button variant="ghost" icon={FlagOff} fullWidth onClick={() => setAction('unflag')}>
                                Retirer le signalement
                            </Button>
                        ) : (
                            <Button variant="ghost" icon={Flag} fullWidth onClick={() => setAction('flag')}>
                                Signaler ce compte
                            </Button>
                        )}
                    </div>
                )}

                {moderation.actions.length > 0 && (
                    <ol className="relative mt-5 space-y-4 border-l-2 border-gray-100 pl-5">
                        {moderation.actions.map((item) => (
                            <li key={item.id} className="relative">
                                <span className="absolute -left-[1.6rem] top-1 h-3 w-3 rounded-full bg-gray-300 ring-4 ring-white" aria-hidden="true" />
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge color={item.type_color} size="sm">{item.type_label}</Badge>
                                    {item.reason_label && <span className="text-sm font-medium text-gray-800">{item.reason_label}</span>}
                                </div>
                                <p className="mt-0.5 text-xs text-gray-500">
                                    <time dateTime={item.at_iso}>{item.at}</time> · {item.admin}
                                    {item.ends_at && ` · jusqu’au ${item.ends_at}`}
                                    {item.acknowledged_at && ` · lu le ${item.acknowledged_at}`}
                                </p>
                                {item.message && <p className="mt-1 rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700">{item.message}</p>}
                                {item.internal_note && (
                                    <p className="mt-1 rounded-lg bg-purple-50 px-2.5 py-1.5 text-xs text-purple-900">
                                        <span className="font-semibold">Note interne :</span> {item.internal_note}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ol>
                )}
            </Card>

            {action && <ModerationDialog action={action} accountId={account.id} moderation={moderation} onClose={() => setAction(null)} />}
        </>
    );
}
