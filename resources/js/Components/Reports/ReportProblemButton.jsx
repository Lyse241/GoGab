import Button from '@/Components/UI/Button';
import Modal from '@/Components/UI/Modal';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import { useForm } from '@inertiajs/react';
import { Flag, Send } from 'lucide-react';
import { useId, useState } from 'react';

/**
 * « Signaler un problème » sur une commande : fenêtre avec la personne concernée (l'autre
 * partie de la commande), le motif et une description obligatoire. La personne signalée n'est
 * jamais prévenue ; l'équipe Gogab traite le signalement.
 *
 * - reporting : prop du serveur (ReportService::formFor) ; rien n'est affiché si null
 * - size / variant / className : apparence du bouton
 */
export default function ReportProblemButton({ reporting, size = 'sm', variant = 'ghost', className, label = 'Signaler un problème' }) {
    const [open, setOpen] = useState(false);
    const formId = useId();
    const available = reporting?.parties.filter((party) => !party.already_reported) ?? [];
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        reported_user_id: '',
        reason: '',
        description: '',
    });

    if (!reporting) {
        return null;
    }

    const openForm = () => {
        clearErrors();
        reset();
        // Une seule personne possible : présélectionnée.
        if (available.length === 1) {
            setData('reported_user_id', String(available[0].id));
        }
        setOpen(true);
    };

    const close = () => {
        if (!processing) {
            setOpen(false);
        }
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('reports.store', reporting.order_id), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <>
            <Button size={size} variant={variant} icon={Flag} className={className} onClick={openForm}>
                {label}
            </Button>

            <Modal
                open={open}
                onClose={close}
                closeable={!processing}
                title="Signaler un problème"
                description="Votre signalement est envoyé à l’équipe Gogab, jamais à la personne concernée."
                footer={
                    <>
                        <Button variant="outline" onClick={close} disabled={processing}>
                            Annuler
                        </Button>
                        <Button type="submit" form={formId} icon={Send} loading={processing} disabled={available.length === 0}>
                            Envoyer le signalement
                        </Button>
                    </>
                }
            >
                {available.length === 0 ? (
                    <p className="rounded-xl bg-info-50 px-3 py-3 text-sm text-info-900 ring-1 ring-info-100">
                        Vous avez déjà signalé un problème pour cette commande : notre équipe s’en occupe et vous préviendra.
                    </p>
                ) : (
                    <form id={formId} onSubmit={submit} noValidate className="space-y-4">
                        <Select
                            id="report-person"
                            label="Personne concernée"
                            required
                            placeholder="Choisissez"
                            options={reporting.parties.map((party) => ({
                                value: String(party.id),
                                label: party.already_reported ? `${party.label} (déjà signalé)` : party.label,
                                disabled: party.already_reported,
                            }))}
                            value={data.reported_user_id}
                            onChange={(event) => setData('reported_user_id', event.target.value)}
                            error={errors.reported_user_id}
                        />
                        <Select
                            id="report-reason"
                            label="Motif"
                            required
                            placeholder="Choisissez un motif"
                            options={reporting.reasons}
                            value={data.reason}
                            onChange={(event) => setData('reason', event.target.value)}
                            error={errors.reason}
                        />
                        <Textarea
                            id="report-description"
                            label="Que s’est-il passé ?"
                            required
                            rows={4}
                            maxLength={2000}
                            placeholder="Décrivez les faits : heure, ce qui a été dit ou fait…"
                            value={data.description}
                            onChange={(event) => setData('description', event.target.value)}
                            error={errors.description}
                        />
                    </form>
                )}
            </Modal>
        </>
    );
}
