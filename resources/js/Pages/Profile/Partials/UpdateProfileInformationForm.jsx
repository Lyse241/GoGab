import NeighborhoodSelect from '@/Components/NeighborhoodSelect';
import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import Input from '@/Components/UI/Input';
import Textarea from '@/Components/UI/Textarea';
import { focusFirstError } from '@/utils/focusFirstError';
import { Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';

/**
 * Informations du compte : nom, téléphone, e-mail, quartier et repères (obligatoires pour un
 * client : adresse de livraison préremplie au checkout).
 */
export default function UpdateProfileInformationForm({ profile, neighborhoods, mustVerifyEmail, status, emailVerified }) {
    const { data, setData, patch, errors, processing, isDirty } = useForm({
        name: profile.name ?? '',
        phone: profile.phone ?? '',
        email: profile.email ?? '',
        neighborhood_id: profile.neighborhood_id ?? '',
        address_landmarks: profile.address_landmarks ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route('profile.update'), { preserveScroll: true, onError: focusFirstError });
    };

    return (
        <Card>
            <CardHeader title="Mes informations" description="Elles sont visibles par les autres parties de vos commandes (prénom, téléphone)." />
            <form onSubmit={submit} noValidate className="space-y-4">
                <Input id="name" label="Nom complet" required autoComplete="name" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} />
                <div className="grid gap-4 sm:grid-cols-2">
                    <Input
                        id="phone"
                        type="tel"
                        inputMode="tel"
                        label="Téléphone"
                        required
                        autoComplete="tel"
                        placeholder="077 12 34 56"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                        error={errors.phone}
                    />
                    <Input id="email" type="email" label="E-mail" required autoComplete="email" value={data.email} onChange={(e) => setData('email', e.target.value)} error={errors.email} />
                </div>

                {mustVerifyEmail && !emailVerified && (
                    <p className="rounded-xl bg-warning-50 px-3 py-2 text-sm text-warning-900">
                        Votre adresse e-mail n’est pas vérifiée.{' '}
                        <Link href={route('verification.send')} method="post" as="button" className="font-semibold underline">
                            Renvoyer le lien de vérification
                        </Link>
                        {status === 'verification-link-sent' && <span className="mt-1 block font-medium text-success-800">Un nouveau lien vous a été envoyé.</span>}
                    </p>
                )}

                <NeighborhoodSelect
                    id="neighborhood_id"
                    label="Quartier"
                    required={profile.address_required}
                    placeholder={profile.address_required ? 'Choisissez votre quartier' : 'Aucun'}
                    neighborhoods={neighborhoods}
                    value={data.neighborhood_id ?? ''}
                    onChange={(e) => setData('neighborhood_id', e.target.value)}
                    error={errors.neighborhood_id}
                    hint={profile.address_required ? 'Adresse de livraison proposée par défaut.' : undefined}
                />
                <Textarea
                    id="address_landmarks"
                    label="Adresse et repères"
                    required={profile.address_required}
                    rows={3}
                    maxLength={500}
                    placeholder="Ex. : après la pharmacie, portail bleu, 2e maison à gauche"
                    value={data.address_landmarks ?? ''}
                    onChange={(e) => setData('address_landmarks', e.target.value)}
                    error={errors.address_landmarks}
                />

                <Button type="submit" icon={Save} loading={processing} disabled={!isDirty}>
                    Enregistrer
                </Button>
            </form>
        </Card>
    );
}
