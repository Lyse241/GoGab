import NeighborhoodSelect from '@/Components/NeighborhoodSelect';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import StatusBadge from '@/Components/UI/StatusBadge';
import DeliveryLayout from '@/Layouts/DeliveryLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Bike, ExternalLink, FileText, MapPin, Pencil, Save, Upload, UserRound } from 'lucide-react';
import { useMemo } from 'react';

function Row({ label, children }) {
    return (
        <div className="flex justify-between gap-4 py-2.5 text-sm">
            <dt className="text-gray-600">{label}</dt>
            <dd className="min-w-0 break-words text-right font-medium text-gray-900">{children || '—'}</dd>
        </div>
    );
}

/**
 * Profil du livreur : informations personnelles, véhicule, quartier de base (zone des offres)
 * et statut de chaque document. Nom, e-mail, téléphone et mot de passe : page /profile.
 */
export default function Profile({ account, vehicle, baseNeighborhoodId, neighborhoods, documents }) {
    const { courier } = usePage().props;
    const form = useForm({ base_neighborhood_id: baseNeighborhoodId ?? '' });

    // Zone du quartier choisi dans la liste (avant enregistrement).
    const selectedZone = useMemo(
        () => neighborhoods.find((neighborhood) => String(neighborhood.id) === String(form.data.base_neighborhood_id))?.zone,
        [neighborhoods, form.data.base_neighborhood_id],
    );
    const changed = String(form.data.base_neighborhood_id) !== String(baseNeighborhoodId ?? '');

    const submit = (event) => {
        event.preventDefault();
        form.patch(route('delivery.profile.base-neighborhood'), { preserveScroll: true });
    };

    return (
        <DeliveryLayout title="Mon profil" availability={false}>
            <Head title="Mon profil" />

            {/* Zone d'activité */}
            <Card>
                <CardHeader
                    title="Zone d’activité"
                    description="Vous recevez les offres des commerces situés dans la zone de votre quartier de base."
                    action={<MapPin className="h-5 w-5 text-gray-400" aria-hidden="true" />}
                />
                <div className="mb-4 flex flex-wrap items-center gap-2 rounded-xl bg-secondary-50 px-3 py-2.5 text-sm">
                    <span className="text-gray-600">Zone actuelle :</span>
                    {courier?.zone ? (
                        <span className="font-semibold text-secondary-900">
                            {courier.zone} <span className="font-normal text-gray-600">({courier.base_neighborhood})</span>
                        </span>
                    ) : (
                        <span className="font-semibold text-danger-700">aucune</span>
                    )}
                </div>
                <form onSubmit={submit} className="space-y-3">
                    <NeighborhoodSelect
                        id="base_neighborhood_id"
                        label="Quartier de base"
                        neighborhoods={neighborhoods}
                        value={form.data.base_neighborhood_id}
                        onChange={(event) => form.setData('base_neighborhood_id', event.target.value)}
                        error={form.errors.base_neighborhood_id}
                        hint={changed && selectedZone ? `Nouvelle zone d’activité : ${selectedZone}` : undefined}
                        required
                    />
                    <Button type="submit" icon={Save} loading={form.processing} disabled={!changed} fullWidth>
                        Enregistrer le quartier
                    </Button>
                </form>
            </Card>

            {/* Informations personnelles */}
            <Card>
                <CardHeader
                    title="Informations personnelles"
                    action={<UserRound className="h-5 w-5 text-gray-400" aria-hidden="true" />}
                />
                <dl className="divide-y divide-gray-100">
                    <Row label="Nom">{account.name}</Row>
                    <Row label="Téléphone">{account.phone}</Row>
                    <Row label="E-mail">{account.email}</Row>
                    <Row label="Compte">
                        <StatusBadge type="account" status={account.status} size="sm" />
                    </Row>
                    {account.approved_at && <Row label="Validé le">{account.approved_at}</Row>}
                </dl>
                <Button href={route('profile.edit')} variant="outline" icon={Pencil} fullWidth className="mt-3">
                    Modifier mes informations ou mon mot de passe
                </Button>
            </Card>

            {/* Véhicule */}
            <Card>
                <CardHeader title="Véhicule" action={<Bike className="h-5 w-5 text-gray-400" aria-hidden="true" />} />
                <dl className="divide-y divide-gray-100">
                    <Row label="Type">{vehicle.type}</Row>
                    <Row label="Marque">{vehicle.brand}</Row>
                    {vehicle.plate_number && <Row label="Plaque">{vehicle.plate_number}</Row>}
                    {vehicle.license_number && <Row label="Permis">{vehicle.license_number}</Row>}
                </dl>
            </Card>

            {/* Documents */}
            <Card>
                <CardHeader title="Documents" action={<FileText className="h-5 w-5 text-gray-400" aria-hidden="true" />} />
                <ul className="divide-y divide-gray-100">
                    {documents.map((document) => (
                        <li key={document.type} className="py-3">
                            <div className="flex items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-gray-900">{document.label}</p>
                                    {!document.required && <p className="text-xs text-gray-500">Facultatif</p>}
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    {document.status ? (
                                        <Badge color={document.status_color} size="sm">
                                            {document.status_label}
                                        </Badge>
                                    ) : (
                                        <Badge color="danger" size="sm">
                                            Manquant
                                        </Badge>
                                    )}
                                    {document.url && (
                                        <a
                                            href={document.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex h-tap w-tap items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-secondary"
                                            aria-label={`Voir ${document.label}`}
                                        >
                                            <ExternalLink className="h-4 w-4" aria-hidden="true" />
                                        </a>
                                    )}
                                </div>
                            </div>
                            {document.status === 'rejected' && document.rejection_reason && (
                                <p className="mt-1.5 rounded-lg bg-danger-50 px-2.5 py-1.5 text-xs text-danger-700">Motif : {document.rejection_reason}</p>
                            )}
                        </li>
                    ))}
                </ul>
                <Button href={route('profile.edit')} variant="outline" icon={Upload} fullWidth className="mt-3">
                    Renvoyer ou remplacer un document
                </Button>
            </Card>
        </DeliveryLayout>
    );
}
