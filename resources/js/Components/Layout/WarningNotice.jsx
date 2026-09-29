import Button from '@/Components/UI/Button';
import Modal from '@/Components/UI/Modal';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';

/**
 * Avertissement de la modération non encore lu : affiché à la connexion suivante,
 * il ne se ferme qu'avec « J'ai compris » (accusé de réception enregistré).
 */
export default function WarningNotice() {
    const { moderation } = usePage().props;
    const warning = moderation?.pending_warning;
    const [sending, setSending] = useState(false);

    if (!warning) {
        return null;
    }

    const acknowledge = async () => {
        setSending(true);
        try {
            await axios.post(route('account.warnings.acknowledge', warning.id));
        } finally {
            setSending(false);
            // S'il reste un autre avertissement non lu, il s'affiche à son tour.
            router.reload({ only: ['moderation'] });
        }
    };

    return (
        <Modal open closeable={false} size="md" title="Avertissement de l’équipe Gogab">
            <div className="flex items-start gap-3">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-warning-50 text-warning-600">
                    <TriangleAlert className="h-6 w-6" aria-hidden="true" />
                </span>
                <div className="min-w-0 text-sm">
                    <p className="font-semibold text-gray-900">{warning.reason}</p>
                    <p className="mt-1 whitespace-pre-line text-gray-700">{warning.message}</p>
                    <p className="mt-2 text-xs text-gray-500">Envoyé le {warning.at}</p>
                </div>
            </div>
            <p className="mt-4 rounded-xl bg-gray-50 p-3 text-xs text-gray-600">
                En cas de nouveaux manquements, votre compte pourra être bloqué. Retrouvez vos avertissements dans{' '}
                <Link href={route('account.warnings')} className="font-medium text-secondary underline">
                    Mes avertissements
                </Link>
                .
            </p>
            <Button className="mt-4" fullWidth loading={sending} onClick={acknowledge}>
                J’ai compris
            </Button>
        </Modal>
    );
}
