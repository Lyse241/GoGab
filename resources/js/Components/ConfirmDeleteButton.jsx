import Modal from '@/Components/Modal';
import Spinner from '@/Components/Spinner';
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
                    <h2 className="text-lg font-semibold text-secondary">{title}</h2>
                    <p className="mt-2 text-sm text-gray-600">{message}</p>
                    <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="rounded-full border border-secondary-200 px-5 py-2 text-sm font-semibold text-secondary hover:bg-secondary-50"
                        >
                            Annuler
                        </button>
                        <button
                            type="button"
                            onClick={confirm}
                            disabled={processing}
                            aria-busy={processing}
                            className="inline-flex items-center justify-center gap-2 rounded-full bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            {processing && <Spinner />}
                            Supprimer définitivement
                        </button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
