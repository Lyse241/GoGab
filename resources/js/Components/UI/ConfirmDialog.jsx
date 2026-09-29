import Button from '@/Components/UI/Button';
import Modal from '@/Components/UI/Modal';
import { cn } from '@/utils/cn';
import { CircleHelp, TriangleAlert } from 'lucide-react';

/**
 * Demande de confirmation avant une action (suppression, refus, annulation…).
 *
 * - variant : "danger" (défaut, action destructrice) ou "primary"
 * - loading : bouton de confirmation en attente, fermeture bloquée
 */
export default function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    title,
    message,
    confirmLabel = 'Confirmer',
    cancelLabel = 'Annuler',
    variant = 'danger',
    loading = false,
    children,
}) {
    const Icon = variant === 'danger' ? TriangleAlert : CircleHelp;

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="sm"
            closeable={!loading}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={loading}>
                        {cancelLabel}
                    </Button>
                    <Button variant={variant} onClick={onConfirm} loading={loading}>
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col items-center text-center sm:flex-row sm:items-start sm:text-left">
                <span
                    className={cn(
                        'flex h-12 w-12 shrink-0 items-center justify-center rounded-full',
                        variant === 'danger' ? 'bg-danger-50 text-danger-600' : 'bg-secondary-50 text-secondary',
                    )}
                >
                    <Icon className="h-6 w-6" aria-hidden="true" />
                </span>
                <div className="mt-3 sm:ml-4 sm:mt-0">
                    <h2 className="text-lg font-semibold text-secondary-900">{title}</h2>
                    {message && <p className="mt-1.5 text-sm text-gray-600">{message}</p>}
                    {children}
                </div>
            </div>
        </Modal>
    );
}
