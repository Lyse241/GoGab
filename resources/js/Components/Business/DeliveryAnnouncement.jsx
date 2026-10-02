import Button from '@/Components/UI/Button';
import Modal from '@/Components/UI/Modal';
import Textarea from '@/Components/UI/Textarea';
import { cn } from '@/utils/cn';
import { router } from '@inertiajs/react';
import { Ban, Megaphone, RotateCw } from 'lucide-react';
import { useEffect, useId, useState } from 'react';

function formatElapsed(seconds) {
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return minutes > 0 ? `${minutes} min ${String(rest).padStart(2, '0')} s` : `${rest} s`;
}

/**
 * Temps écoulé depuis la publication : la valeur vient du serveur (jamais l'horloge du
 * navigateur), puis avance localement chaque seconde jusqu'au prochain rafraîchissement.
 */
function useElapsed(serverSeconds) {
    const [elapsed, setElapsed] = useState(serverSeconds);

    useEffect(() => {
        const receivedAt = Date.now();
        setElapsed(serverSeconds);
        const timer = setInterval(() => setElapsed(serverSeconds + Math.floor((Date.now() - receivedAt) / 1000)), 1000);

        return () => clearInterval(timer);
    }, [serverSeconds]);

    return elapsed;
}

/**
 * Commande en recherche de livreur : « Recherche d'un livreur… » avec le temps écoulé. Après le
 * délai prévu sans réponse, l'entreprise peut relancer l'annonce ou annuler la commande (motif
 * obligatoire, transmis au client). Le serveur revérifie le délai.
 */
export default function DeliveryAnnouncement({ order, className }) {
    const announcement = order.announcement;
    const elapsed = useElapsed(announcement?.elapsed_seconds ?? 0);
    const [busy, setBusy] = useState(null);
    const [cancelling, setCancelling] = useState(false);
    const [reason, setReason] = useState('');
    const [error, setError] = useState(null);
    const reasonId = useId();

    if (!announcement) {
        return null;
    }

    const late = elapsed >= announcement.retry_after_seconds;
    const remaining = Math.max(0, announcement.retry_after_seconds - elapsed);

    const relaunch = () =>
        router.post(
            route('business.orders.relaunch', order.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy('relaunch'),
                onFinish: () => setBusy(null),
            },
        );

    const cancel = () => {
        if (!reason.trim()) {
            setError('Indiquez le motif de l’annulation : il sera transmis au client.');
            return;
        }

        router.put(
            route('orders.status.update', order.id),
            { status: 'annulee', note: reason.trim() },
            {
                preserveScroll: true,
                onStart: () => setBusy('cancel'),
                onFinish: () => setBusy(null),
                onSuccess: () => {
                    setCancelling(false);
                    setReason('');
                },
                onError: (errors) => setError(errors.note ?? errors.status ?? null),
            },
        );
    };

    return (
        <div className={cn('rounded-xl px-3 py-3 ring-1', late ? 'bg-warning-50 ring-warning-200' : 'bg-info-50 ring-info-100', className)}>
            <div className="flex items-start gap-3" role="status">
                <span className="relative mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-secondary">
                    {!late && <span className="absolute inset-0 animate-ping rounded-full bg-info-200 opacity-60 motion-reduce:hidden" aria-hidden="true" />}
                    <Megaphone className="relative h-4 w-4" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1 text-sm">
                    <p className="font-semibold text-secondary-900">Recherche d’un livreur…</p>
                    <p className="text-gray-700">
                        Annonce publiée il y a <span className="font-semibold tabular-nums">{formatElapsed(elapsed)}</span>
                        {announcement.count > 1 && ` · relancée ${announcement.count - 1} fois`}
                    </p>
                    <p className="mt-0.5 text-xs text-gray-600">
                        {late
                            ? 'Aucun livreur n’a encore accepté. Relancez l’annonce ou annulez la commande.'
                            : `Relance possible dans ${formatElapsed(remaining)} si aucun livreur n’accepte.`}
                    </p>
                </div>
            </div>

            {late && (
                <div className="mt-3 flex flex-wrap gap-2">
                    <Button size="sm" icon={RotateCw} onClick={relaunch} loading={busy === 'relaunch'} disabled={busy !== null}>
                        Relancer l’annonce
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        icon={Ban}
                        className="text-danger-700"
                        disabled={busy !== null}
                        onClick={() => {
                            setError(null);
                            setCancelling(true);
                        }}
                    >
                        Annuler la commande
                    </Button>
                </div>
            )}

            <Modal
                open={cancelling}
                onClose={() => setCancelling(false)}
                closeable={busy === null}
                title={`Annuler la commande ${order.number}`}
                description="Aucun livreur n’a pris la course. Le client sera prévenu avec ce motif."
                footer={
                    <>
                        <Button variant="outline" onClick={() => setCancelling(false)} disabled={busy !== null}>
                            Retour
                        </Button>
                        <Button variant="danger" icon={Ban} onClick={cancel} loading={busy === 'cancel'}>
                            Annuler la commande
                        </Button>
                    </>
                }
            >
                <Textarea
                    id={reasonId}
                    label="Motif de l’annulation"
                    required
                    rows={3}
                    maxLength={500}
                    placeholder="Ex. : aucun livreur disponible ce soir, nous ne pouvons pas livrer…"
                    value={reason}
                    onChange={(event) => {
                        setReason(event.target.value);
                        setError(null);
                    }}
                    error={error}
                />
            </Modal>
        </div>
    );
}
