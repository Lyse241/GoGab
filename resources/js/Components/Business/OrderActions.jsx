import Button from '@/Components/UI/Button';
import Modal from '@/Components/UI/Modal';
import Textarea from '@/Components/UI/Textarea';
import { cn } from '@/utils/cn';
import { router } from '@inertiajs/react';
import { Check, ChefHat, Megaphone, X } from 'lucide-react';
import { useId, useState } from 'react';

// Libellés des actions proposées par OrderWorkflow à l'entreprise.
const ACTIONS = {
    acceptee: { label: 'Accepter', icon: Check, variant: 'primary' },
    en_preparation: { label: 'Marquer « En préparation »', icon: ChefHat, variant: 'secondary' },
    en_recherche_livreur: { label: 'Commande prête : chercher un livreur', icon: Megaphone, variant: 'primary' },
    refusee: { label: 'Refuser', icon: X, variant: 'outline' },
};

/**
 * Boutons d'action d'une commande pour l'entreprise (liste des actions = OrderWorkflow).
 * Refuser ouvre une fenêtre : motif obligatoire, transmis au client.
 */
export default function OrderActions({ order, className, size = 'md' }) {
    const [busy, setBusy] = useState(null);
    const [refusing, setRefusing] = useState(false);
    const [reason, setReason] = useState('');
    const [error, setError] = useState(null);
    const reasonId = useId();

    const send = (status, note = null, onSuccess) =>
        router.put(
            route('orders.status.update', order.id),
            { status, note },
            {
                preserveScroll: true,
                onStart: () => setBusy(status),
                onFinish: () => setBusy(null),
                onSuccess,
                onError: (errors) => setError(errors.note ?? errors.status ?? null),
            },
        );

    const refuse = () => {
        if (!reason.trim()) {
            setError('Indiquez le motif du refus : il sera transmis au client.');
            return;
        }

        send('refusee', reason.trim(), () => {
            setRefusing(false);
            setReason('');
        });
    };

    if (!order.actions?.length) {
        return null;
    }

    return (
        <>
            <div className={cn('flex flex-wrap gap-2', className)}>
                {order.actions.map((status) => {
                    const action = ACTIONS[status];
                    if (!action) {
                        return null;
                    }

                    return (
                        <Button
                            key={status}
                            size={size}
                            variant={action.variant}
                            icon={action.icon}
                            loading={busy === status}
                            disabled={busy !== null}
                            className={cn(status === 'refusee' && 'text-danger-700')}
                            onClick={() => (status === 'refusee' ? (setError(null), setRefusing(true)) : send(status))}
                        >
                            {action.label}
                        </Button>
                    );
                })}
            </div>

            <Modal
                open={refusing}
                onClose={() => setRefusing(false)}
                closeable={busy === null}
                title={`Refuser la commande ${order.number}`}
                description="Le client sera prévenu avec ce motif."
                footer={
                    <>
                        <Button variant="outline" onClick={() => setRefusing(false)} disabled={busy !== null}>
                            Annuler
                        </Button>
                        <Button variant="danger" icon={X} onClick={refuse} loading={busy === 'refusee'}>
                            Refuser la commande
                        </Button>
                    </>
                }
            >
                <Textarea
                    id={reasonId}
                    label="Motif du refus"
                    required
                    rows={3}
                    maxLength={500}
                    placeholder="Ex. : rupture de poulet ce soir, nous fermons plus tôt…"
                    value={reason}
                    onChange={(event) => {
                        setReason(event.target.value);
                        setError(null);
                    }}
                    error={error}
                />
            </Modal>
        </>
    );
}
