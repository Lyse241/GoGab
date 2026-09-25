import Modal from '@/Components/Modal';
import { router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Bouton "Supprimer" avec fenêtre de confirmation, puis requête DELETE Inertia.
 */
export default function ConfirmDeleteButton({ url, title, message, className = '', children = 'Supprimer' }) {
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
                className={`text-sm font-medium text-red-600 hover:underline ${className}`}
            >
                {children}
            </button>

            <Modal show={open} maxWidth="md" onClose={() => setOpen(false)}>
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900">{title}</h2>
                    <p className="mt-2 text-sm text-gray-600">{message}</p>
                    <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Annuler
                        </button>
                        <button
                            type="button"
                            onClick={confirm}
                            disabled={processing}
                            className="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            Supprimer définitivement
                        </button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
