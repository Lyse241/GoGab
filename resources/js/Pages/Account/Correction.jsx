import DocumentUploader from '@/Components/DocumentUploader';
import FormErrors, { focusFirstError } from '@/Components/FormErrors';
import NeighborhoodSelect from '@/Components/NeighborhoodSelect';
import OpeningHoursEditor, { validateOpeningHours } from '@/Components/OpeningHoursEditor';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CircleAlert, FileCheck, Hash, IdCard, Phone, RefreshCw, Send, Store, UserRound } from 'lucide-react';
import { useState } from 'react';

/**
 * Un document du dossier : état actuel, motif de refus, et renvoi (obligatoire s'il a été refusé ou manque).
 */
function DocumentSlot({ slot, value, onChange, error }) {
    const [replacing, setReplacing] = useState(false);
    const current = slot.current;

    // Document validé ou en attente : renvoi facultatif, replié par défaut.
    if (!slot.must_resend && current && !replacing && !value) {
        return (
            <li className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4">
                <div className="flex min-w-0 items-start gap-2">
                    <FileCheck className="mt-0.5 h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" />
                    <div className="min-w-0">
                        <p className="font-semibold text-secondary-900">{slot.spec.label}</p>
                        <p className="truncate text-xs text-gray-500">{current.original_name}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Badge color={current.status_color} size="sm" dot>
                        {current.status_label}
                    </Badge>
                    <Button size="sm" variant="ghost" icon={RefreshCw} onClick={() => setReplacing(true)}>
                        Remplacer
                    </Button>
                </div>
            </li>
        );
    }

    // Document facultatif jamais envoyé : proposé sans obligation.
    return (
        <li>
            {current?.status === 'rejected' && (
                <div className="mb-2 flex items-start gap-2 rounded-xl bg-danger-50 p-3 text-sm text-danger-800">
                    <CircleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                    <p>
                        <span className="font-semibold">{slot.spec.label} refusé :</span> {current.rejection_reason}
                    </p>
                </div>
            )}
            {slot.must_resend && !current && (
                <p className="mb-2 text-sm font-medium text-danger-700">Document manquant : à envoyer.</p>
            )}
            <DocumentUploader spec={slot.spec} value={value} onChange={onChange} error={error} required={slot.must_resend} />
        </li>
    );
}

/**
 * Dossier refusé : l'utilisateur corrige ses informations et renvoie les documents refusés.
 * À l'envoi, le compte repasse « en attente » et les administrateurs sont prévenus.
 */
export default function Correction({ rejectionReason, role, values, vehicle, neighborhoods, categories, documents }) {
    const { data, setData, post, processing, progress, errors, setError, clearErrors, transform } = useForm({
        ...values,
        documents: {},
    });

    const update = (field, value) => {
        setData(field, value);
        clearErrors(field);
    };

    const setDocument = (type, file) => {
        setData('documents', { ...data.documents, [type]: file });
        clearErrors(`documents.${type}`);
    };

    const missing = documents.filter((slot) => slot.must_resend && !data.documents[slot.spec.type]);

    const submit = (event) => {
        event.preventDefault();

        const clientErrors = {
            ...(role === 'business' ? validateOpeningHours(data.opening_hours) : {}),
            ...Object.fromEntries(missing.map((slot) => [`documents.${slot.spec.type}`, `Document manquant : ${slot.spec.label}.`])),
        };
        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);
            focusFirstError(clientErrors);
            return;
        }

        // Seuls les documents ajoutés sont envoyés.
        transform((form) => ({
            ...form,
            documents: Object.fromEntries(Object.entries(form.documents).filter(([, file]) => file)),
        }));

        post(route('account.correction.update'), { forceFormData: true, onError: focusFirstError });
    };

    return (
        <GuestLayout width="lg" title="Corriger mon dossier" subtitle="Modifiez ce qui doit l’être puis renvoyez votre dossier : il sera vérifié à nouveau.">
            <Head title="Corriger mon dossier" />

            <Link href={route('account.rejected')} className="-mt-2 mb-4 inline-flex min-h-tap items-center gap-1.5 text-sm font-medium text-secondary hover:underline">
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Retour
            </Link>

            {rejectionReason && (
                <section className="mb-6 rounded-2xl border border-danger-200 bg-danger-50 p-4">
                    <h2 className="text-sm font-semibold text-danger-800">Motif du refus</h2>
                    <p className="mt-1 whitespace-pre-line text-sm text-danger-900">{rejectionReason}</p>
                </section>
            )}

            <form onSubmit={submit} noValidate className="space-y-6">
                <FormErrors errors={errors} />

                <Card>
                    <CardHeader title={role === 'business' ? 'Le gérant' : 'Vos informations'} action={<UserRound className="h-5 w-5 text-primary-600" aria-hidden="true" />} />
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Input id="name" label="Nom complet" value={data.name} onChange={(e) => update('name', e.target.value)} error={errors.name} autoComplete="name" required />
                            <Input id="phone" type="tel" label="Téléphone" icon={Phone} value={data.phone} onChange={(e) => update('phone', e.target.value)} error={errors.phone} inputMode="tel" required />
                        </div>
                        {role !== 'business' && (
                            <>
                                <NeighborhoodSelect id="neighborhood_id" label="Quartier" neighborhoods={neighborhoods} value={data.neighborhood_id} onChange={(e) => update('neighborhood_id', e.target.value)} error={errors.neighborhood_id} required />
                                <Textarea id="address_landmarks" label="Adresse et repères" rows={3} maxLength={500} value={data.address_landmarks} onChange={(e) => update('address_landmarks', e.target.value)} error={errors.address_landmarks} required />
                            </>
                        )}
                    </div>
                </Card>

                {vehicle && (
                    <Card>
                        <CardHeader title="Votre véhicule" description={`${vehicle.label} (pour changer de type de véhicule, contactez l’équipe Gogab)`} />
                        <div className="space-y-4">
                            <Input id="vehicle_brand" label={vehicle.requires_license ? 'Marque et modèle' : 'Marque et modèle (facultatif)'} value={data.vehicle_brand} onChange={(e) => update('vehicle_brand', e.target.value)} error={errors.vehicle_brand} required={vehicle.requires_license} />
                            {vehicle.requires_license && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Input id="plate_number" label="Plaque d’immatriculation" icon={Hash} value={data.plate_number} onChange={(e) => update('plate_number', e.target.value.toUpperCase())} error={errors.plate_number} required />
                                    <Input id="license_number" label="Numéro de permis" icon={IdCard} value={data.license_number} onChange={(e) => update('license_number', e.target.value)} error={errors.license_number} required />
                                </div>
                            )}
                            <NeighborhoodSelect id="base_neighborhood_id" label="Quartier de base (zone d’activité)" neighborhoods={neighborhoods} value={data.base_neighborhood_id} onChange={(e) => update('base_neighborhood_id', e.target.value)} error={errors.base_neighborhood_id} required />
                        </div>
                    </Card>
                )}

                {role === 'business' && (
                    <Card>
                        <CardHeader title="Le commerce" action={<Store className="h-5 w-5 text-primary-600" aria-hidden="true" />} />
                        <div className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Input id="store_name" label="Nom commercial" value={data.store_name} onChange={(e) => update('store_name', e.target.value)} error={errors.store_name} required />
                                <Select id="category_id" label="Catégorie" placeholder="Choisissez une catégorie" options={categories.map((category) => ({ value: category.id, label: category.name }))} value={data.category_id} onChange={(e) => update('category_id', e.target.value)} error={errors.category_id} required />
                            </div>
                            <Textarea id="description" label="Description courte (facultatif)" rows={2} maxLength={300} value={data.description} onChange={(e) => update('description', e.target.value)} error={errors.description} />
                            <Input id="store_phone" type="tel" label="Téléphone du commerce" icon={Phone} value={data.store_phone} onChange={(e) => update('store_phone', e.target.value)} error={errors.store_phone} inputMode="tel" required />
                            <NeighborhoodSelect id="neighborhood_id" label="Quartier du commerce" neighborhoods={neighborhoods} value={data.neighborhood_id} onChange={(e) => update('neighborhood_id', e.target.value)} error={errors.neighborhood_id} required />
                            <Textarea id="address_landmarks" label="Adresse et repères" rows={3} maxLength={500} value={data.address_landmarks} onChange={(e) => update('address_landmarks', e.target.value)} error={errors.address_landmarks} required />
                            <OpeningHoursEditor value={data.opening_hours} onChange={(days) => update('opening_hours', days)} errors={errors} />
                        </div>
                    </Card>
                )}

                {documents.length > 0 && (
                    <section>
                        <h2 className="text-lg font-bold text-secondary-900">Documents</h2>
                        <p className="mt-1 text-sm text-gray-600">Renvoyez les documents refusés ; les autres peuvent être remplacés si besoin.</p>
                        <ul className="mt-3 space-y-3">
                            {documents.map((slot) => (
                                <DocumentSlot
                                    key={slot.spec.type}
                                    slot={slot}
                                    value={data.documents[slot.spec.type] ?? null}
                                    onChange={(file) => setDocument(slot.spec.type, file)}
                                    error={errors[`documents.${slot.spec.type}`]}
                                />
                            ))}
                        </ul>
                    </section>
                )}

                {missing.length > 0 && (
                    <div role="status" className="flex items-start gap-2 rounded-2xl bg-accent-50 p-3 text-sm text-secondary-900 ring-1 ring-accent-200">
                        <CircleAlert className="mt-0.5 h-4 w-4 shrink-0 text-accent-700" aria-hidden="true" />
                        <p>
                            <span className="font-semibold">À renvoyer :</span> {missing.map((slot) => slot.spec.label).join(', ')}.
                        </p>
                    </div>
                )}

                <Button type="submit" size="lg" fullWidth icon={Send} loading={processing} disabled={missing.length > 0}>
                    {processing && progress ? `Envoi… ${progress.percentage} %` : 'Renvoyer mon dossier'}
                </Button>
            </form>
        </GuestLayout>
    );
}
