import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import { cn } from '@/utils/cn';
import { router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Bouton "Supprimer" avec fenêtre de confirmation, puis requête DELETE Inertia.
 */
export default function ConfirmDeleteButton({ url, title, message, className, children = 'Supprimer' }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        router.delete(url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setOpen(false);
            },
        });
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className={cn('min-h-9 text-sm font-medium text-danger-600 hover:underline', className)}
            >
                {children}
            </button>

            <ConfirmDialog
                open={open}
                onClose={() => setOpen(false)}
                onConfirm={confirm}
                title={title}
                message={message}
                confirmLabel="Supprimer définitivement"
                loading={processing}
            />
        </>
    );
}
